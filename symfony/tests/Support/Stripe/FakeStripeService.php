<?php

declare(strict_types=1);

namespace App\Tests\Support\Stripe;

use App\Entity\Cart;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Product;
use App\Repository\CheckoutRepository;
use App\Service\StripeServiceInterface;
use App\Time\ClockInterface;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Test-only Stripe service that avoids real API calls.
 *
 * Implements StripeServiceInterface while returning synthetic
 * Stripe objects so functional tests can execute without network access.
 */
final readonly class FakeStripeService implements StripeServiceInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlG,
        private ClockInterface $clock,
    ) {
    }

    public function getClient(): StripeClient
    {
        // Return a client with test key - won't make real calls in tests
        return new StripeClient('sk_test_fake');
    }

    public function getReturnUrl(?Event $event): string
    {
        if (null === $event) {
            return $this->urlG->generate(
                'entropy_shop_complete',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ).'?session_id={CHECKOUT_SESSION_ID}';
        }

        return $this->urlG->generate(
            'entropy_event_shop_complete',
            [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ).'?session_id={CHECKOUT_SESSION_ID}';
    }

    public function updateOurProduct(
        Product $product,
        ?StripeObject $stripePrice,
        ?StripeObject $stripeProduct,
    ): Product {
        // If only product provided (no price)
        if (null !== $stripeProduct && null === $stripePrice) {
            $product->setActive(1 == $stripeProduct['active']);
            $product->setNameEn($stripeProduct['name']);
            $product->setStripeId($stripeProduct['id']);

            return $product;
        }

        // Create synthetic objects if needed
        $stripeProduct ??= StripeObject::constructFrom([
            'id' => $product->getStripeId() ?? 'prod_test_'.bin2hex(random_bytes(5)),
            'name' => $product->getNameEn() ?: 'Test product',
            'active' => true,
        ]);

        $stripePrice ??= StripeObject::constructFrom([
            'id' => $product->getStripePriceId() ?? 'price_test_'.bin2hex(random_bytes(5)),
            'product' => $stripeProduct['id'],
            'unit_amount' => $product->getAmount() ?? 0,
            'active' => true,
        ]);

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
        // Parse session ID pattern to determine status
        // cs_test_completed_* → 'complete'
        // cs_test_open_* → 'open'
        // cs_test_expired_* → 'expired'

        $status = 'complete'; // default
        if (str_contains($sessionId, '_open_')) {
            $status = 'open';
        } elseif (str_contains($sessionId, '_expired_')) {
            $status = 'expired';
        }

        return Session::constructFrom([
            'id' => $sessionId,
            'payment_status' => 'complete' === $status ? 'paid' : 'unpaid',
            'status' => $status,
            'customer_email' => 'test@example.com',
        ]);
    }

    /**
     * @return array{stripeSession: Session, checkout: Checkout}
     */
    public function createCheckoutSession(
        Cart $cart,
        Request $request,
        CheckoutRepository $checkoutRepo,
        ?Event $event = null,
    ): array {
        $returnUrl = $this->getReturnUrl($event);
        $expires = $this->clock->now()->modify('+30 minutes');

        // Replicate line item validation from the real service
        $lineItems = [];
        $itemsInCheckout = $checkoutRepo->findProductQuantitiesInOngoingCheckouts();

        foreach ($cart->getProducts() as $cartItem) {
            $productId = $cartItem->getProduct()->getId();
            $minus = $itemsInCheckout[$productId] ?? null;
            $item = $cartItem->getLineItem(null, $minus);

            if (\is_array($item)) {
                $lineItems[] = $item;
            }
        }

        if ([] === $lineItems) {
            throw new \RuntimeException('No valid line items - cart is empty or all products sold out');
        }

        // Synthesize Stripe checkout session object (no external API calls)
        $sessionId = 'cs_test_'.bin2hex(random_bytes(16));
        $stripeSession = Session::constructFrom([
            'id' => $sessionId,
            'client_secret' => $sessionId.'_secret',
            'url' => $returnUrl,
            'payment_status' => 'unpaid',
            'status' => 'open',
            'expires_at' => $expires->getTimestamp(),
        ]);

        // Persist Checkout entity exactly like the production service
        $checkout = new Checkout();
        $checkout->setStripeSessionId($stripeSession['id']);
        $checkout->setCart($cart);
        $checkoutRepo->add($checkout, true);

        $request->getSession()->set('StripeSessionId', $stripeSession['id']);

        return [
            'stripeSession' => $stripeSession,
            'checkout' => $checkout,
        ];
    }
}
