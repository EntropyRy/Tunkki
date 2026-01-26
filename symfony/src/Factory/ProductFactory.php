<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Event;
use App\Entity\Product;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * ProductFactory.
 *
 * Lightweight factory for creating Product entities (tickets, service fees, merchandise) for tests.
 *
 * Goals:
 *  - Provide sensible defaults (active ticket with Stripe test IDs, reasonable inventory & price).
 *  - Offer expressive states for common permutations (ticket, service fee, sold out, inactive).
 *  - Keep performance high: no heavy relation creation by default.
 *
 * Example:
 *   $product = ProductFactory::new()->ticket()->forEvent($event)->create();
 *   $fee = ProductFactory::new()->serviceFee()->create();
 *   $soldOut = ProductFactory::new()->soldOut()->create();
 *
 * NOTE: Stripe IDs use test mode format (prod_test_*, price_test_*) for test isolation.
 *
 * @extends PersistentObjectFactory<Product>
 */
final class ProductFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    /**
     * Default attribute set.
     *
     * We pick deterministic values suitable for ticket purchase testing:
     *  - Stripe test mode IDs (prod_test_*, price_test_*)
     *  - Active ticket product by default
     *  - Reasonable inventory (50 units) and price (€15.00)
     *  - Bilingual names
     *  - Purchase limit of 10 per transaction
     */
    protected function defaults(): callable
    {
        return static function (): array {
            $uniqueSuffix = bin2hex(random_bytes(8));
            $nameBase = self::faker()->words(2, true);

            return [
                'stripeId' => 'prod_test_'.$uniqueSuffix,
                'stripePriceId' => 'price_test_'.$uniqueSuffix,
                'quantity' => 50,
                'active' => true,
                'amount' => 1500, // €15.00 in cents
                'event' => null,
                'ticket' => true,
                'serviceFee' => false,
                'descriptionFi' => self::faker()->sentence(8),
                'descriptionEn' => self::faker()->sentence(8),
                'picture' => null,
                'nameEn' => ucfirst($nameBase).' Ticket',
                'nameFi' => ucfirst($nameBase).' Lippu',
                'howManyOneCanBuyAtOneTime' => 10,
            ];
        };
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Product $product): void {
            // Ensure lifecycle callbacks are respected (createdAt/updatedAt auto-set)
            // No additional normalization needed for now
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Explicitly mark as a ticket product (already default, provided for clarity).
     */
    public function ticket(): static
    {
        return $this->with([
            'ticket' => true,
            'serviceFee' => false,
        ]);
    }

    /**
     * Mark as a service fee product (auto-added to cart at checkout).
     */
    public function serviceFee(): static
    {
        return $this->with([
            'ticket' => false,
            'serviceFee' => true,
            'nameEn' => 'Service Fee',
            'nameFi' => 'Palvelumaksu',
            'amount' => 150, // €1.50
        ]);
    }

    /**
     * Mark product as sold out (quantity equals zero or very low with high sales simulation).
     */
    public function soldOut(): static
    {
        return $this->with([
            'quantity' => 0,
            'active' => true, // Still active, just no inventory
        ]);
    }

    /**
     * Mark product as inactive (not shown in shop).
     */
    public function inactive(): static
    {
        return $this->with([
            'active' => false,
        ]);
    }

    /**
     * Create a general store product (not linked to any event).
     * General store products are shown in /kauppa (shop) route.
     * General store products are NOT tickets - they're merchandise/other items.
     */
    public function generalStore(): static
    {
        return $this->with([
            'event' => null,
            'active' => true,
            'serviceFee' => false,
            'ticket' => false, // General store sells non-ticket products
        ]);
    }

    /**
     * Set specific quantity available.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->with(['quantity' => $quantity]);
    }

    /**
     * Set purchase limit per transaction.
     */
    public function withPurchaseLimit(int $limit): static
    {
        return $this->with(['howManyOneCanBuyAtOneTime' => $limit]);
    }

    /**
     * Set product price in cents.
     */
    public function withPrice(int $amountCents): static
    {
        return $this->with(['amount' => $amountCents]);
    }

    /* -----------------------------------------------------------------
     * Relations
     * ----------------------------------------------------------------- */

    /**
     * Link product to a specific event.
     */
    public function forEvent(Event $event): static
    {
        return $this->with(['event' => $event]);
    }
}
