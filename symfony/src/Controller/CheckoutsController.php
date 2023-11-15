<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Helper\AppStripeClient;
use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutsController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/{year}/{slug}/kassa',
            'en' => '/{year}/{slug}/checkout',
        ],
        name: 'event_stripe_checkouts'
    )]
    public function index(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        AppStripeClient $stripe,
        CheckoutRepository $cRepo,
        CartRepository $cartR,
        ProductRepository $pRepo
    ): Response {
        $client = $stripe->getClient();
        $returnUrl = $stripe->getReturnUrl($event);
        $session = $request->getSession();
        $cartId = $session->get('cart');
        $cart = $cartR->findOneBy(['id' => $cartId]);
        $expires = new \DateTime('+30min');
        $products = $cart->getProducts();
        $lineItems = [];
        $itemsInCheckout = $cRepo->findProductQuantitiesInOngoingCheckouts();
        foreach ($products as $cartItem) {
            $minus = array_key_exists($cartItem->getProduct()->getId(), $itemsInCheckout) ? $itemsInCheckout[$cartItem->getProduct()->getId()] : null;
            $item = $cartItem->getLineItem(null, $minus);
            if (is_array($item)) {
                $lineItems[] = $item;
            } else {
                $this->addFlash('warning', 'product.sold_out');
            }
        }
        if (count($lineItems) > 0) {
            $eventServiceFeeProduct = $pRepo->findEventServiceFee($event);
            if ($eventServiceFeeProduct != null) {
                $cartItem = new CartItem();
                $cartItem->setProduct($eventServiceFeeProduct);
                $cartItem->setQuantity(1);
                $cart->addProduct($cartItem);
                $lineItems[] = $cartItem->getLineItem(1, null);
            }
            $stripeSession = $client->checkout->sessions->create([
                'ui_mode' => 'embedded',
                'line_items' => [$lineItems],
                'mode' => 'payment',
                'return_url' => $returnUrl,
                'automatic_tax' => [
                    'enabled' => true,
                ],
                'customer_email' => $cart->getEmail(),
                'expires_at' => $expires->format('U'),
                'locale' => $request->getLocale()
            ]);
            $checkout = new Checkout();
            $checkout->setStripeSessionId($stripeSession['id']);
            $checkout->setCart($cart);
            $cRepo->add($checkout, true);
            $session->set('StripeSessionId', $stripeSession['id']);
        } else {
            $this->addFlash('warning', 'e30v.cart.empty');
            return $this->redirectToRoute('entropy_event_shop', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        return $this->render('event/checkouts.html.twig', [
            'stripeSession' => $stripeSession,
            'event' => $event,
            'publicKey' => $this->getParameter('stripe_public_key')
        ]);
    }
}
