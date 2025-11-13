<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\EventTemporalStateService;
use App\Entity\Cart;
use App\Entity\Event;
use App\Entity\User;
use App\Form\CartType;
use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Service\NakkiDisplayService;
use App\Service\StripeService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * EventShopController - Handles the complete commerce flow for event tickets and products.
 *
 * Flow: Shop (cart) → Checkout (payment) → Complete (confirmation)
 */
class EventShopController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly EventTemporalStateService $eventTemporalState,
        private readonly NakkiDisplayService $nakkiDisplay,
    ) {
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/kauppa',
                'en' => '/{year}/{slug}/shop',
            ],
            name: 'entropy_event_shop',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function shop(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        CartRepository $cartR,
        CheckoutRepository $checkoutR,
        NakkiBookingRepository $nakkirepo,
        TicketRepository $ticketRepo,
    ): Response {
        if (!$this->eventTemporalState->isPresaleOpen($event)) {
            throw $this->createAccessDeniedException('');
        }
        $selected = [];
        $nakkis = [];
        $hasNakki = false;
        $email = null;
        $user = $this->getUser();
        if (
            (!$this->eventTemporalState->isPublished($event)
                && !$user instanceof UserInterface)
            || (!$user instanceof UserInterface
                && $event->isNakkiRequiredForTicketReservation())
        ) {
            throw $this->createAccessDeniedException('');
        }
        if (null != $user) {
            \assert($user instanceof User);
            $email = $user->getEmail();
            $member = $user->getMember();
            $selected = $nakkirepo->findMemberEventBookings($member, $event);
            $nakkis = $this->nakkiDisplay->getNakkiFromGroup(
                $event,
                $member,
                $selected,
                $request->getLocale(),
            );
            $hasNakki = [] !== (array) $selected;
        }
        $session = $request->getSession();
        $cart = new Cart();
        $cartId = $session->get('cart');
        if (null != $cartId) {
            $cart = $cartR->findOneBy(['id' => $cartId]);
            if (null == $cart) {
                $cart = new Cart();
            }
        }
        if (null == $cart->getEmail()) {
            $cart->setEmail($email);
        }
        $products = $event->getProducts();
        $max = $checkoutR->findProductQuantitiesInOngoingCheckouts();
        // check that user does not have the product already
        // if user has the product, remove it from the list
        foreach ($products as $key => $product) {
            // if there can be only one ticket per user, check that user does not have the ticket already
            $minus = \array_key_exists($product->getId(), $max)
                ? $max[$product->getId()]
                : 0;
            if (
                1 == $product->getHowManyOneCanBuyAtOneTime()
                && $product->getMax($minus) >= 1
                && $product->isTicket()
                && null != $email
            ) {
                foreach (
                    $ticketRepo->findTicketsByEmailAndEvent($email, $event) as $ticket
                ) {
                    if (
                        $ticket->getStripeProductId() == $product->getStripeId()
                    ) {
                        unset($products[$key]);
                    }
                }
            }
        }
        $cart->setProducts($products);
        $form = $this->createForm(CartType::class, $cart);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $cart = $form->getData();
            $cartR->save($cart, true);
            $session->set('cart', $cart->getId());

            return $this->redirectToRoute('event_stripe_checkouts', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
        }
        // if use clicks on the login button then redirect them back to this page
        $this->saveTargetPath($session, 'main', $request->getUri());

        // Calculate total sold tickets (count all tickets for this event)
        $totalSold = 0;
        foreach ($event->getTickets() as $ticket) {
            if ('paid' === $ticket->getStatus()) {
                ++$totalSold;
            }
        }

        return $this->render('event/shop.html.twig', [
            'selected' => $selected,
            'nakkis' => $nakkis,
            'hasNakki' => $hasNakki,
            'nakkiRequired' => $event->isNakkiRequiredForTicketReservation(),
            'event' => $event,
            'form' => $form,
            'inCheckouts' => $max,
            'totalSold' => $totalSold,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/kassa',
                'en' => '/{year}/{slug}/checkout',
            ],
            name: 'event_stripe_checkouts',
        ),
    ]
    public function checkout(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        StripeService $stripe,
        CheckoutRepository $cRepo,
        CartRepository $cartR,
    ): Response {
        // Retrieve cart from session
        $cartId = $request->getSession()->get('cart');
        $cart = $cartR->findOneBy(['id' => $cartId]);

        if (null === $cart) {
            $this->addFlash('warning', 'cart.empty');

            return $this->redirectToRoute('entropy_event_shop', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
        }

        // Check for sold-out products and add flash messages
        $itemsInCheckout = $cRepo->findProductQuantitiesInOngoingCheckouts();
        foreach ($cart->getProducts() as $cartItem) {
            $productId = $cartItem->getProduct()->getId();
            $minus = $itemsInCheckout[$productId] ?? null;
            $item = $cartItem->getLineItem(null, $minus);

            if (!\is_array($item)) {
                $this->addFlash('warning', 'product.sold_out');
            }
        }

        // Create checkout session via StripeService
        try {
            $result = $stripe->createCheckoutSession($cart, $request, $cRepo, $event);
        } catch (\RuntimeException) {
            $this->addFlash('warning', 'cart.empty');

            return $this->redirectToRoute('entropy_event_shop', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
        }

        return $this->render('event/checkouts.html.twig', [
            'stripeSession' => $result['stripeSession'],
            'event' => $event,
            'publicKey' => $this->getParameter('stripe_public_key'),
            'time' => $result['checkout']->getUpdatedAt()->format('U'),
            'email' => $cart->getEmail(),
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/valmis',
                'en' => '/{year}/{slug}/complete',
            ],
            name: 'entropy_event_shop_complete',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function complete(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        StripeService $stripe,
        CheckoutRepository $cRepo,
    ): Response {
        $sessionId = $request->get('session_id');
        $stripeSession = $stripe->getCheckoutSession($sessionId);
        if ('open' == $stripeSession->status) {
            $this->addFlash('warning', 'e30v.checkout.open');

            return $this->redirectToRoute('event_stripe_checkouts', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
        }
        $email = '';
        if ('complete' == $stripeSession->status) {
            $checkout = $cRepo->findOneBy(['stripeSessionId' => $sessionId]);
            $cart = $checkout->getCart();
            $email = $cart->getEmail();
            $request->getSession()->remove('cart');
        }

        return $this->render('event/shop_complete.html.twig', [
            'event' => $event,
            'email' => $email,
        ]);
    }
}
