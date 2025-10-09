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
        $item = $this->createMock(CartItem::class);
        $item->expects($this->any())->method('setCart');
        $item->expects($this->any())->method('getCart')->willReturn($cart);

        $cart->addProduct($item);
        $this->assertTrue($cart->getProducts()->contains($item));

        $cart->removeProduct($item);
        $this->assertFalse($cart->getProducts()->contains($item));
    }

    public function testClearProducts(): void
    {
        $cart = new Cart();
        $item1 = $this->createMock(CartItem::class);
        $item2 = $this->createMock(CartItem::class);

        $cart->addProduct($item1);
        $cart->addProduct($item2);

        $this->assertCount(2, $cart->getProducts());
        $cart->clearProducts();
        $this->assertCount(0, $cart->getProducts());
    }

    public function testSetProductsOnlyAddsActiveTickets(): void
    {
        $cart = new Cart();

        // Use real Product objects
        $activeProduct = new \App\Entity\Product();
        $activeProduct->setActive(true);
        $activeProduct->setTicket(true);

        $inactiveProduct = new \App\Entity\Product();
        $inactiveProduct->setActive(false);
        $inactiveProduct->setTicket(true);

        $nonTicketProduct = new \App\Entity\Product();
        $nonTicketProduct->setActive(true);
        $nonTicketProduct->setTicket(false);

        // setProducts expects Product objects, not CartItem objects
        $cart->setProducts([$activeProduct, $inactiveProduct, $nonTicketProduct]);

        // Only active tickets should be added
        $this->assertCount(1, $cart->getProducts());
    }

    public function testAddAndRemoveCheckout(): void
    {
        $cart = new Cart();
        $checkout = $this->createMock(Checkout::class);
        $checkout->expects($this->any())->method('setCart');
        $checkout->expects($this->any())->method('getCart')->willReturn($cart);

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
