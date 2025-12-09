<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Checkout;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Cart
 */
final class CartEntityTest extends TestCase
{
    public function testConstructorInitializesCollections(): void
    {
        $cart = new Cart();
        $this->assertInstanceOf(ArrayCollection::class, $cart->getProducts());
        $this->assertInstanceOf(ArrayCollection::class, $cart->getCheckouts());
        $this->assertCount(0, $cart->getProducts());
        $this->assertCount(0, $cart->getCheckouts());
    }

    public function testSetAndGetEmail(): void
    {
        $cart = new Cart();
        $cart->setEmail('test@example.com');
        $this->assertSame('test@example.com', $cart->getEmail());

        $cart->setEmail(null);
        $this->assertNull($cart->getEmail());
    }

    public function testAddAndRemoveProduct(): void
    {
        $cart = new Cart();
        $item = $this->createStub(CartItem::class);

        $cart->addProduct($item);
        $this->assertTrue($cart->getProducts()->contains($item));

        $cart->removeProduct($item);
        $this->assertFalse($cart->getProducts()->contains($item));
    }

    public function testClearProducts(): void
    {
        $cart = new Cart();
        $item1 = $this->createStub(CartItem::class);
        $item2 = $this->createStub(CartItem::class);

        $cart->addProduct($item1);
        $cart->addProduct($item2);

        $this->assertCount(2, $cart->getProducts());
        $cart->clearProducts();
        $this->assertCount(0, $cart->getProducts());
    }

    public function testSetProductsOnlyAddsActiveProducts(): void
    {
        $cart = new Cart();

        // Use real Product objects
        $activeTicket = new \App\Entity\Product();
        $activeTicket->setActive(true);
        $activeTicket->setTicket(true);

        $inactiveProduct = new \App\Entity\Product();
        $inactiveProduct->setActive(false);
        $inactiveProduct->setTicket(true);

        $activeGeneralProduct = new \App\Entity\Product();
        $activeGeneralProduct->setActive(true);
        $activeGeneralProduct->setTicket(false);

        // setProducts expects Product objects, not CartItem objects
        $cart->setProducts([$activeTicket, $inactiveProduct, $activeGeneralProduct]);

        // Only active products should be added (both tickets and general store items)
        $this->assertCount(2, $cart->getProducts());
    }

    public function testAddAndRemoveCheckout(): void
    {
        $cart = new Cart();
        $checkout = $this->createStub(Checkout::class);

        $cart->addCheckout($checkout);
        $this->assertTrue($cart->getCheckouts()->contains($checkout));

        $cart->removeCheckout($checkout);
        $this->assertFalse($cart->getCheckouts()->contains($checkout));
    }

    public function testCollectionGenerics(): void
    {
        $cart = new Cart();
        $products = $cart->getProducts();
        $checkouts = $cart->getCheckouts();

        $this->assertInstanceOf(ArrayCollection::class, $products);
        $this->assertInstanceOf(ArrayCollection::class, $checkouts);

        foreach ($products as $product) {
            $this->assertInstanceOf(CartItem::class, $product);
        }
        foreach ($checkouts as $checkout) {
            $this->assertInstanceOf(Checkout::class, $checkout);
        }
    }

    public function testEdgeCaseSetters(): void
    {
        $cart = new Cart();
        $cart->setEmail(null);
        $cart->clearProducts();
        $cart->getProducts()->clear();
        $cart->getCheckouts()->clear();

        $this->assertNull($cart->getEmail());
        $this->assertCount(0, $cart->getProducts());
        $this->assertCount(0, $cart->getCheckouts());
    }
}
