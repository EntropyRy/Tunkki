<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * CheckoutsController - Handles checkout for the general store only.
 * Event checkout has been moved to EventShopController.
 */
class CheckoutsController extends AbstractController
{
    #[
        Route(
            path: [
                'fi' => '/kassa',
                'en' => '/checkout',
            ],
            name: 'stripe_checkout',
        ),
    ]
    public function checkout(
        Request $request,
        StripeService $stripe,
        CheckoutRepository $cRepo,
        CartRepository $cartR,
    ): Response {
        // Retrieve cart from session
        $cartId = $request->getSession()->get('cart');
        $cart = $cartR->findOneBy(['id' => $cartId]);

        if (null === $cart) {
            $this->addFlash('warning', 'shop.cart.empty');

            return $this->redirectToRoute('entropy_shop', []);
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

        // Create checkout session via StripeService (no event for general shop)
        try {
            $result = $stripe->createCheckoutSession($cart, $request, $cRepo);
        } catch (\RuntimeException) {
            $this->addFlash('warning', 'shop.cart.empty');

            return $this->redirectToRoute('entropy_shop', []);
        }

        return $this->render('shop/checkout.html.twig', [
            'stripeSession' => $result['stripeSession'],
            'publicKey' => $this->getParameter('stripe_public_key'),
            'time' => $result['checkout']->getUpdatedAt()->format('U'),
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/kauppa/valmis',
                'en' => '/shop/complete',
            ],
            name: 'entropy_shop_complete',
        ),
    ]
    public function complete(
        Request $request,
        StripeService $stripe,
        CheckoutRepository $cRepo,
    ): Response {
        $sessionId = $request->get('session_id');
        $stripeSession = $stripe->getCheckoutSession($sessionId);

        if ('open' == $stripeSession->status) {
            $this->addFlash('warning', 'checkout.still_open');

            return $this->redirectToRoute('stripe_checkout');
        }

        $email = '';
        if ('complete' == $stripeSession->status) {
            $checkout = $cRepo->findOneBy(['stripeSessionId' => $sessionId]);
            $cart = $checkout->getCart();
            $email = $cart->getEmail();
            $request->getSession()->remove('cart');
        }

        return $this->render('shop/complete.html.twig', [
            'email' => $email,
        ]);
    }
}
