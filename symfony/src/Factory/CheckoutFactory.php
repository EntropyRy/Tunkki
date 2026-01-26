<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Cart;
use App\Entity\Checkout;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * CheckoutFactory.
 *
 * Lightweight factory for creating Checkout entities (Stripe payment sessions).
 *
 * Goals:
 *  - Provide sensible defaults (Stripe test session ID, open status).
 *  - Offer state methods for common checkout statuses (completed, expired, processed).
 *  - Keep performance high: no heavy relation creation by default.
 *
 * Example:
 *   $checkout = CheckoutFactory::new()->forCart($cart)->create();
 *   $completed = CheckoutFactory::new()->completed()->create();
 *   $expired = CheckoutFactory::new()->expired()->create();
 *
 * Status codes:
 *  - 0 = open (payment in progress)
 *  - 1 = completed (payment successful, awaiting ticket creation)
 *  - -1 = expired (session timeout, payment not completed)
 *  - 2 = processed (tickets created and sent)
 *
 * @extends PersistentObjectFactory<Checkout>
 */
final class CheckoutFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Checkout::class;
    }

    /**
     * Default attribute set.
     *
     * We pick deterministic values suitable for checkout testing:
     *  - Stripe test mode session ID (cs_test_*)
     *  - Status = 0 (open/in-progress)
     *  - Cart initially null (link via forCart helper)
     */
    protected function defaults(): callable
    {
        return static function (): array {
            $uniqueSuffix = bin2hex(random_bytes(16));

            return [
                'stripeSessionId' => 'cs_test_'.$uniqueSuffix,
                'status' => 0, // 0 = open
                'cart' => null,
            ];
        };
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Checkout $checkout): void {
            // Lifecycle callbacks (createdAt/updatedAt) are handled by the entity
            // No additional normalization needed
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Mark checkout as completed (payment successful, awaiting fulfillment).
     * Status = 1.
     */
    public function completed(): static
    {
        return $this->with([
            'stripeSessionId' => 'cs_test_completed_'.bin2hex(random_bytes(8)),
            'status' => 1,
        ]);
    }

    /**
     * Mark checkout as expired (session timed out before payment).
     * Status = -1.
     */
    public function expired(): static
    {
        return $this->with([
            'stripeSessionId' => 'cs_test_expired_'.bin2hex(random_bytes(8)),
            'status' => -1,
        ]);
    }

    /**
     * Mark checkout as processed (tickets created and sent to customer).
     * Status = 2.
     */
    public function processed(): static
    {
        return $this->with(['status' => 2]);
    }

    /**
     * Explicitly mark checkout as open (in-progress payment).
     * This is already the default, provided for clarity in test intent.
     * Status = 0.
     */
    public function open(): static
    {
        return $this->with([
            'stripeSessionId' => 'cs_test_open_'.bin2hex(random_bytes(8)),
            'status' => 0,
        ]);
    }

    /**
     * Set a specific Stripe session ID (useful for webhook testing).
     */
    public function withStripeSessionId(string $sessionId): static
    {
        return $this->with(['stripeSessionId' => $sessionId]);
    }

    /* -----------------------------------------------------------------
     * Relations
     * ----------------------------------------------------------------- */

    /**
     * Link this checkout to a specific cart.
     */
    public function forCart(Cart $cart): static
    {
        // Foundry automatically handles proxy unwrapping
        return $this->with(['cart' => $cart]);
    }
}
