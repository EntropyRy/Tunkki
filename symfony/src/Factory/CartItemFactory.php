<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * CartItemFactory.
 *
 * Lightweight factory for creating CartItem entities (line items in shopping carts).
 *
 * Goals:
 *  - Provide sensible defaults (quantity=1, product/cart=null for manual linking).
 *  - Offer helpers for linking to Product and Cart.
 *  - Keep performance high: no heavy relation creation by default.
 *
 * Example:
 *   $item = CartItemFactory::new()->forProduct($product)->forCart($cart)->create();
 *   $multipleTickets = CartItemFactory::new()->forProduct($product)->withQuantity(5)->create();
 *
 * @extends PersistentObjectFactory<CartItem>
 */
final class CartItemFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return CartItem::class;
    }

    /**
     * Default attribute set.
     *
     * We pick deterministic values suitable for cart item testing:
     *  - Default quantity of 1
     *  - Product and Cart initially null (link via forProduct/forCart helpers)
     */
    protected function defaults(): callable
    {
        return static fn (): array => [
            'quantity' => 1,
            'product' => null,
            'cart' => null,
        ];
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (CartItem $cartItem): void {
            // No additional normalization needed
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Set a specific quantity for this cart item.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->with(['quantity' => $quantity]);
    }

    /* -----------------------------------------------------------------
     * Relations
     * ----------------------------------------------------------------- */

    /**
     * Link this cart item to a specific product.
     */
    public function forProduct(Product $product): static
    {
        // Foundry automatically handles proxy unwrapping
        return $this->with(['product' => $product]);
    }

    /**
     * Link this cart item to a specific cart.
     *
     * Note: Usually you'd add items via Cart::addProduct() or CartFactory::withItems()
     * rather than setting the cart directly on the item. This helper is provided for
     * explicit test scenarios where direct linkage is needed.
     */
    public function forCart(Cart $cart): static
    {
        // Foundry automatically handles proxy unwrapping
        return $this->with(['cart' => $cart]);
    }
}
