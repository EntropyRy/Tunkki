<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\EventPublicationDecider;
use App\Entity\Cart;
use App\Entity\Event;
use App\Entity\User;
use App\Form\CartType;
use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Repository\NakkiBookingRepository;
use App\Repository\ProductRepository;
use App\Repository\TicketRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * ShopsController - Handles shop routes for both event-specific and general store.
 *
 * Consolidates shop logic:
 * - Event shop: Products linked to specific events
 * - General store: Products not linked to events (future use)
 */
class ShopsController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly EventPublicationDecider $publicationDecider,
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
    public function eventShop(
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
        if (false === $event->ticketPresaleEnabled()) {
            throw $this->createAccessDeniedException('');
        }
        $selected = [];
        $nakkis = [];
        $hasNakki = false;
        $email = null;
        $user = $this->getUser();
        if (
            (!$this->publicationDecider->isPublished($event)
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
            $nakkis = $this->getNakkiFromGroup(
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

        return $this->render('event/shop.html.twig', [
            'selected' => $selected,
            'nakkis' => $nakkis,
            'hasNakki' => $hasNakki,
            'nakkiRequired' => $event->isNakkiRequiredForTicketReservation(),
            'event' => $event,
            'form' => $form,
            'inCheckouts' => $max,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/kauppa',
                'en' => '/shop',
            ],
            name: 'entropy_shop',
        ),
    ]
    public function shop(
        Request $request,
        ProductRepository $productRepo,
        CartRepository $cartR,
        CheckoutRepository $checkoutR,
    ): Response {
        // General store: products NOT linked to events
        $user = $this->getUser();
        $email = null;

        if (null != $user) {
            \assert($user instanceof User);
            $email = $user->getEmail();
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

        // Fetch general store products (not linked to events)
        $products = $productRepo->findGeneralStoreProducts();
        $max = $checkoutR->findProductQuantitiesInOngoingCheckouts();

        $cart->setProducts($products);
        $form = $this->createForm(CartType::class, $cart);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cart = $form->getData();
            $cartR->save($cart, true);
            $session->set('cart', $cart->getId());

            return $this->redirectToRoute('stripe_checkout');
        }

        // Save target path for login redirect
        $this->saveTargetPath($session, 'main', $request->getUri());

        return $this->render('shop/index.html.twig', [
            'form' => $form,
            'inCheckouts' => $max,
        ]);
    }

    /**
     * Get available nakki bookings for a member at an event.
     */
    protected function getNakkiFromGroup(
        Event $event,
        $member,
        $selected,
        string $locale,
    ): array {
        $nakkis = [];
        foreach ($event->getNakkis() as $nakki) {
            if (true == $nakki->isDisableBookings()) {
                continue;
            }
            foreach ($selected as $booking) {
                if ($booking->getNakki() == $nakki) {
                    $nakkis = $this->addNakkiToArray(
                        $nakkis,
                        $booking,
                        $locale,
                    );
                    break;
                }
            }
            if (
                !\array_key_exists(
                    $nakki->getDefinition()->getName($locale),
                    $nakkis,
                )
            ) {
                // try to prevent displaying same nakki to 2 different users using the system at the same time
                $bookings = $nakki->getNakkiBookings()->toArray();
                shuffle($bookings);
                foreach ($bookings as $booking) {
                    if (null === $booking->getMember()) {
                        $nakkis = $this->addNakkiToArray(
                            $nakkis,
                            $booking,
                            $locale,
                        );
                        break;
                    }
                }
            }
        }

        return $nakkis;
    }

    /**
     * Add a nakki booking to the nakkis array.
     */
    protected function addNakkiToArray(array $nakkis, $booking, string $locale): array
    {
        $name = $booking->getNakki()->getDefinition()->getName($locale);
        $duration = $booking
            ->getStartAt()
            ->diff($booking->getEndAt())
            ->format('%h');
        $nakkis[$name]['description'] = $booking
            ->getNakki()
            ->getDefinition()
            ->getDescription($locale);
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;

        return $nakkis;
    }
}
