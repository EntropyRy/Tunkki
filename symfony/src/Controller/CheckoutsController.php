<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Service\StripeService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutsController extends AbstractController
{
    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/kassa',
                'en' => '/{year}/{slug}/checkout',
            ],
            name: 'event_stripe_checkouts',
        ),
    ]
    public function eventCheckout(
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
            $result = $stripe->createCheckoutSession($cart, $request, $cRepo, null);
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
}
