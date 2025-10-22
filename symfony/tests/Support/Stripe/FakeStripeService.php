<?php

declare(strict_types=1);

namespace App\Tests\Support\Stripe;

use App\Entity\Cart;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Product;
use App\Repository\CheckoutRepository;
use App\Service\StripeService;
use Stripe\Checkout\Session;
use Stripe\StripeObject;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test-only Stripe service that avoids real API calls.
 *
 * Mimics the behaviour of the production service while returning synthetic
 * Stripe objects so functional tests can execute without network access.
 */
readonly class FakeStripeService extends StripeService
{
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
        $expires = new \DateTimeImmutable('+30min');

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

    public function getCheckoutSession($sessionId): Session
    {
        return Session::constructFrom([
            'id' => $sessionId,
            'payment_status' => 'paid',
            'status' => 'complete',
        ]);
    }

    public function updateOurProduct(
        Product $product,
        ?StripeObject $stripePrice,
        ?StripeObject $stripeProduct,
    ): Product {
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

        return parent::updateOurProduct($product, $stripePrice, $stripeProduct);
    }
}
