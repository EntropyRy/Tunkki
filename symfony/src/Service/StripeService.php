<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Product;
use App\Repository\CheckoutRepository;
use App\Time\ClockInterface;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class StripeService implements StripeServiceInterface
{
    public function __construct(
        private ParameterBagInterface $bag,
        private UrlGeneratorInterface $urlG,
        private ClockInterface $clock,
    ) {
    }

    public function getClient(): StripeClient
    {
        return new StripeClient($this->bag->get('stripe_secret_key'));
    }

    public function getReturnUrl(?Event $event): string
    {
        if (null == $event) {
            return $this->urlG->generate(
                'entropy_shop_complete',
                [],
                $this->urlG::ABSOLUTE_URL,
            ).'?session_id={CHECKOUT_SESSION_ID}';
        }

        return $this->urlG->generate(
            'entropy_event_shop_complete',
            [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ],
            $this->urlG::ABSOLUTE_URL,
        ).'?session_id={CHECKOUT_SESSION_ID}';
    }

    public function updateOurProduct(
        Product $product,
        ?StripeObject $stripePrice,
        ?StripeObject $stripeProduct,
    ): Product {
        if (null != $stripeProduct && null == $stripePrice) {
            $product->setActive(1 == $stripeProduct['active']);
            $product->setNameEn($stripeProduct['name']);
            $product->setStripeId($stripeProduct['id']);

            return $product;
        }
        if (null == $stripeProduct && null != $stripePrice) {
            $stripeProduct = $this->getClient()->products->retrieve(
                $stripePrice['product'],
            );
        }
        $active = true;
        if (0 == $stripeProduct['active'] || 0 == $stripePrice['active']) {
            $active = false;
        }
        $product->setAmount($stripePrice['unit_amount']);
        $product->setActive($active);
        $product->setNameEn($stripeProduct['name']);
        $product->setStripeId($stripeProduct['id']);
        $product->setStripePriceId($stripePrice['id']);

        return $product;
    }

    public function getCheckoutSession($sessionId): Session
    {
        $stripe = $this->getClient();

        return $stripe->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Create a Stripe checkout session and persist Checkout entity.
     *
     * @param Cart               $cart         The shopping cart
     * @param Request            $request      Current HTTP request (for session & locale)
     * @param CheckoutRepository $checkoutRepo Repository for persisting Checkout
     * @param ?Event             $event        Optional event (for return URL generation)
     *
     * @return array{stripeSession: Session, checkout: Checkout} Created session and checkout entity
     */
    public function createCheckoutSession(
        Cart $cart,
        Request $request,
        CheckoutRepository $checkoutRepo,
        ?Event $event = null,
    ): array {
        $client = $this->getClient();
        $returnUrl = $this->getReturnUrl($event);
        $expires = $this->clock->now()->modify('+30 minutes');

        // Build line items from cart
        $lineItems = [];
        $itemsInCheckout = $checkoutRepo->findProductQuantitiesInOngoingCheckouts();

        foreach ($cart->getProducts() as $cartItem) {
            $productId = $cartItem->getProduct()->getId();
            $minus = $itemsInCheckout[$productId] ?? null;
            $item = $cartItem->getLineItem(null, $minus);

            if (\is_array($item)) {
                $lineItems[] = $item;
            }
            // Note: sold-out handling (flash messages) happens in controller
        }

        if ([] === $lineItems) {
            throw new \RuntimeException('No valid line items - cart is empty or all products sold out');
        }

        // Create Stripe checkout session
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
            'locale' => $request->getLocale(),
        ]);

        // Create and persist Checkout entity
        $checkout = new Checkout();
        $checkout->setStripeSessionId($stripeSession['id']);
        $checkout->setCart($cart);
        $checkoutRepo->add($checkout, true);

        // Store session ID in session for later retrieval
        $request->getSession()->set('StripeSessionId', $stripeSession['id']);

        return [
            'stripeSession' => $stripeSession,
            'checkout' => $checkout,
        ];
    }
}
