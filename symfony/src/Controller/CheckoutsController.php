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
    public function eventCheckout(
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
        if ($cart == null) {
            $this->addFlash('warning', 'e30v.cart.empty');
            return $this->redirectToRoute('entropy_event_shop', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        $expires = new \DateTime('+30min');
        $products = $cart->getProducts();
        $lineItems = [];
        $itemsInCheckout = $cRepo->findProductQuantitiesInOngoingCheckouts();
        foreach ($products as $cartItem) {
            $minus = $itemsInCheckout[$cartItem->getProduct()->getId()] ?? null;
            $item = $cartItem->getLineItem(null, $minus);
            if (is_array($item)) {
                $lineItems[] = $item;
            } else {
                $this->addFlash('warning', 'product.sold_out');
            }
        }
        if ($lineItems !== []) {
            $eventServiceFeeProduct = $pRepo->findEventServiceFee($event);
            if ($eventServiceFeeProduct != null) {
                $found = array_any($products->toArray(), fn ($cartItem): bool => $cartItem->getProduct()->getId() === $eventServiceFeeProduct->getId());
                if (!$found) {
                    $cartItem = new CartItem();
                    $cartItem->setProduct($eventServiceFeeProduct);
                    $cartItem->setQuantity(1);
                    $cart->addProduct($cartItem);
                    $lineItems[] = $cartItem->getLineItem(1, null);
                }
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
                'expires_at' => $expires->getTimestamp(),
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
            'publicKey' => $this->getParameter('stripe_public_key'),
            'time' => $checkout->getUpdatedAt()->format('U')
        ]);
    }
    #[Route(
        path: [
            'fi' => '/kassa',
            'en' => '/checkout',
        ],
        name: 'stripe_checkout'
    )]
    public function checkout(
        Request $request,
        AppStripeClient $stripe,
        CheckoutRepository $cRepo,
        CartRepository $cartR,
        ProductRepository $pRepo
    ): Response {
        $client = $stripe->getClient();
        $returnUrl = $stripe->getReturnUrl(null);
        $session = $request->getSession();
        $cartId = $session->get('cart');
        $cart = $cartR->findOneBy(['id' => $cartId]);
        if ($cart == null) {
            $this->addFlash('warning', 'shop.cart.empty');
            return $this->redirectToRoute('entropy_shop', []);
        }
        $expires = new \DateTime('+30min');
        $products = $cart->getProducts();
        $lineItems = [];
        $itemsInCheckout = $cRepo->findProductQuantitiesInOngoingCheckouts();
        foreach ($products as $cartItem) {
            $minus = $itemsInCheckout[$cartItem->getProduct()->getId()] ?? null;
            $item = $cartItem->getLineItem(null, $minus);
            if (is_array($item)) {
                $lineItems[] = $item;
            } else {
                $this->addFlash('warning', 'product.sold_out');
            }
        }
        if ($lineItems !== []) {
            $stripeSession = $client->checkout->sessions->create([
                'ui_mode' => 'embedded',
                'line_items' => [$lineItems],
                'mode' => 'payment',
                'return_url' => $returnUrl,
                'automatic_tax' => [
                    'enabled' => true,
                ],
                'customer_email' => $cart->getEmail(),
                'expires_at' => $expires->getTimestamp(),
                'locale' => $request->getLocale()
            ]);
            $checkout = new Checkout();
            $checkout->setStripeSessionId($stripeSession['id']);
            $checkout->setCart($cart);
            $cRepo->add($checkout, true);
            $session->set('StripeSessionId', $stripeSession['id']);
        } else {
            $this->addFlash('warning', 'shop.cart.empty');
            return $this->redirectToRoute('entropy_shop', []);
        }
        return $this->render('shop/checkout.html.twig', [
            'stripeSession' => $stripeSession,
            'publicKey' => $this->getParameter('stripe_public_key'),
            'time' => $checkout->getUpdatedAt()->format('U')
        ]);
    }
}
