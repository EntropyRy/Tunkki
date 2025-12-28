<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Product;
use App\Repository\CheckoutRepository;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for Stripe payment service.
 *
 * Provides methods for:
 * - Creating and retrieving checkout sessions
 * - Updating product information from Stripe
 * - Generating return URLs
 */
interface StripeServiceInterface
{
    /**
     * Get a configured Stripe client.
     */
    public function getClient(): StripeClient;

    /**
     * Get the return URL for a checkout session.
     *
     * @param Event|null $event Optional event for event-specific URLs
     */
    public function getReturnUrl(?Event $event): string;

    /**
     * Update a local Product entity with data from Stripe.
     *
     * @param Product           $product       The local product to update
     * @param StripeObject|null $stripePrice   Stripe price object
     * @param StripeObject|null $stripeProduct Stripe product object
     */
    public function updateOurProduct(
        Product $product,
        ?StripeObject $stripePrice,
        ?StripeObject $stripeProduct,
    ): Product;

    /**
     * Retrieve a Stripe checkout session by ID.
     *
     * @param string $sessionId The Stripe session ID
     */
    public function getCheckoutSession($sessionId): Session;

    /**
     * Create a Stripe checkout session and persist Checkout entity.
     *
     * @param Cart               $cart         The shopping cart
     * @param Request            $request      Current HTTP request (for session & locale)
     * @param CheckoutRepository $checkoutRepo Repository for persisting Checkout
     * @param Event|null         $event        Optional event (for return URL generation)
     *
     * @return array{stripeSession: Session, checkout: Checkout} Created session and checkout entity
     */
    public function createCheckoutSession(
        Cart $cart,
        Request $request,
        CheckoutRepository $checkoutRepo,
        ?Event $event = null,
    ): array;
}
