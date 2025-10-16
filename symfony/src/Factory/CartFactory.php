<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Cart;
use App\Entity\CartItem;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * CartFactory.
 *
 * Lightweight factory for creating Cart entities for ticket purchase tests.
 *
 * Goals:
 *  - Provide sensible defaults (valid customer email, empty products collection).
 *  - Offer helpers for adding CartItems.
 *  - Keep performance high: no heavy relation creation by default.
 *
 * Example:
 *   $cart = CartFactory::new()->create();
 *   $cartWithItems = CartFactory::new()->withItems([$item1, $item2])->create();
 *   $empty = CartFactory::new()->empty()->create();
 *
 * @extends PersistentObjectFactory<Cart>
 */
final class CartFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Cart::class;
    }

    /**
     * Default attribute set.
     *
     * We pick deterministic values suitable for cart testing:
     *  - Valid email address
     *  - Empty products collection (add via withItems() or withCartItem())
     */
    protected function defaults(): callable
    {
        return static fn (): array => [
            'email' => self::faker()->unique()->safeEmail(),
        ];
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Cart $cart): void {
            // Collections are auto-initialized by entity constructor
            // No additional normalization needed
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Explicitly create an empty cart (no items).
     * This is already the default, provided for clarity in test intent.
     */
    public function empty(): static
    {
        return $this; // Default state is already empty
    }

    /**
     * Set a specific customer email.
     */
    public function withEmail(string $email): static
    {
        return $this->with(['email' => $email]);
    }

    /* -----------------------------------------------------------------
     * Relations
     * ----------------------------------------------------------------- */

    /**
     * Add multiple CartItems to the cart.
     *
     * @param CartItem[] $items Array of CartItem entities (can be Foundry proxies or plain entities)
     */
    public function withItems(array $items): static
    {
        return $this->afterInstantiate(function (Cart $cart) use ($items): void {
            foreach ($items as $item) {
                // Foundry automatically handles proxy unwrapping
                // Cart::addProduct() sets the bidirectional relationship
                $cart->addProduct($item);
            }
            // No need to manually persist - Foundry handles cascade persist
        });
    }

    /**
     * Add a single CartItem to the cart.
     */
    public function withCartItem(CartItem $item): static
    {
        return $this->withItems([$item]);
    }
}
