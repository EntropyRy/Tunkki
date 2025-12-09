<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProductFactory;
use App\Repository\CartRepository;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * GeneralShopCartSubmissionTest.
 *
 * Tests cart form submission for the general store (non-event products).
 * Verifies:
 * - Anonymous user cart submission with email
 * - Logged-in member cart submission (email pre-filled)
 * - Form validation (invalid email, zero quantities)
 * - Cart persistence and redirect to checkout
 * - Multiple product selection
 *
 * Roadmap alignment:
 * - CLAUDE.md §4: Factory-driven, structural assertions
 * - CLAUDE.md §8: Negative coverage (form validation tests)
 * - CLAUDE.md §21.1: Site-aware client for multisite routing
 */
final class GeneralShopCartSubmissionTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testAnonymousUserCanSubmitCartWithEmail(): void
    {
        $product = ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Yleinen Tuote',
            'quantity' => 50,
            'amount' => 2000, // €20.00
        ]);

        $crawler = $this->client->request('GET', '/kauppa');
        $this->assertResponseIsSuccessful();

        // Extract form and CSRF token
        $form = $crawler->filter('form[name="cart"]')->form();
        $csrfToken = $form['cart[_token]']->getValue();

        // Submit form with email and product quantity
        $this->client->request('POST', '/kauppa', [
            'cart' => [
                '_token' => $csrfToken,
                'email' => 'anonymous@example.com',
                'products' => [
                    0 => [
                        'quantity' => 2,
                        'product' => (string) $product->getId(),
                    ],
                ],
            ],
        ]);

        // Should redirect to checkout
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isRedirect(),
            'Should redirect to checkout route',
        );

        $location = $response->headers->get('Location') ?? '';
        $this->assertTrue(
            str_contains($location, '/checkout') || str_contains($location, '/kassa'),
            'Should redirect to checkout route (/checkout or /kassa)',
        );

        // Verify cart was saved
        $cartRepo = static::getContainer()->get(CartRepository::class);
        $carts = $cartRepo->findBy(['email' => 'anonymous@example.com']);
        $this->assertCount(
            1,
            $carts,
            'Cart should be saved for anonymous user',
        );

        $cart = $carts[0];
        $this->assertSame('anonymous@example.com', $cart->getEmail());
        $this->assertCount(
            1,
            $cart->getProducts(),
            'Cart should have 1 product',
        );

        $cartItem = $cart->getProducts()->first();
        $this->assertSame(2, $cartItem->getQuantity());
        $this->assertSame($product->getId(), $cartItem->getProduct()->getId());
    }

    public function testLoggedInMemberCanSubmitCart(): void
    {
        [$user, $client] = $this->loginAsMember('member@example.com');
        $this->seedClientHome('fi');
        self::$client = $client;

        $product1 = ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Tuote 1',
            'quantity' => 100,
            'amount' => 1500,
        ]);

        $product2 = ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Tuote 2',
            'quantity' => 50,
            'amount' => 2500,
        ]);

        $crawler = $client->request('GET', '/kauppa');
        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Shop should be accessible to logged-in member',
        );

        $form = $crawler->filter('form[name="cart"]')->form();
        $csrfToken = $form['cart[_token]']->getValue();

        // Submit with multiple products
        $client->request('POST', '/kauppa', [
            'cart' => [
                '_token' => $csrfToken,
                'email' => $user->getEmail(),
                'products' => [
                    0 => [
                        'quantity' => 1,
                        'product' => (string) $product1->getId(),
                    ],
                    1 => [
                        'quantity' => 3,
                        'product' => (string) $product2->getId(),
                    ],
                ],
            ],
        ]);

        $this->assertTrue(
            $client->getResponse()->isRedirect(),
            'Should redirect after cart submission',
        );

        // Verify cart
        $cartRepo = static::getContainer()->get(CartRepository::class);
        $carts = $cartRepo->findBy(['email' => $user->getEmail()]);
        $this->assertGreaterThanOrEqual(
            1,
            \count($carts),
            'Cart should be saved',
        );

        $cart = $carts[0];
        $this->assertSame($user->getEmail(), $cart->getEmail());
        $this->assertGreaterThanOrEqual(
            2,
            $cart->getProducts()->count(),
            'Cart should have multiple products',
        );
    }

    public function testInvalidEmailRejected(): void
    {
        $product = ProductFactory::new()->generalStore()->create(['quantity' => 10]);

        $crawler = $this->client->request('GET', '/kauppa');
        $form = $crawler->filter('form[name="cart"]')->form();
        $csrfToken = $form['cart[_token]']->getValue();

        $this->client->request('POST', '/kauppa', [
            'cart' => [
                '_token' => $csrfToken,
                'email' => 'not-an-email', // Invalid email
                'products' => [
                    0 => [
                        'quantity' => 1,
                        'product' => (string) $product->getId(),
                    ],
                ],
            ],
        ]);

        // Should result in HTTP 422 Unprocessable Entity for invalid email
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            422,
            $statusCode,
            'Invalid email should result in HTTP 422 Unprocessable Entity',
        );

        // No cart should be created with invalid email
        $cartRepo = static::getContainer()->get(CartRepository::class);
        $carts = $cartRepo->findBy(['email' => 'not-an-email']);
        $this->assertCount(
            0,
            $carts,
            'No cart should be created with invalid email',
        );
    }

    public function testZeroQuantityProductsFilteredOut(): void
    {
        [$user, $client] = $this->loginAsMember();
        $this->seedClientHome('fi');
        self::$client = $client;

        $product1 = ProductFactory::new()->generalStore()->create(['quantity' => 20]);
        $product2 = ProductFactory::new()->generalStore()->create(['quantity' => 30]);

        $crawler = $client->request('GET', '/kauppa');
        $form = $crawler->filter('form[name="cart"]')->form();
        $csrfToken = $form['cart[_token]']->getValue();

        // Submit with one product at quantity=0, one at quantity=2
        $client->request('POST', '/kauppa', [
            'cart' => [
                '_token' => $csrfToken,
                'email' => $user->getEmail(),
                'products' => [
                    0 => [
                        'quantity' => 0, // Should be filtered out
                        'product' => (string) $product1->getId(),
                    ],
                    1 => [
                        'quantity' => 2,
                        'product' => (string) $product2->getId(),
                    ],
                ],
            ],
        ]);

        $this->assertTrue(
            $client->getResponse()->isRedirect(),
            'Should redirect after cart submission',
        );

        // Verify only non-zero quantity product in cart
        $cartRepo = static::getContainer()->get(CartRepository::class);
        $carts = $cartRepo->findBy(['email' => $user->getEmail()]);
        $cart = $carts[0];

        // CartType has delete_empty which should remove zero-quantity items
        foreach ($cart->getProducts() as $cartItem) {
            $this->assertGreaterThan(
                0,
                $cartItem->getQuantity(),
                'Cart should only contain items with quantity > 0',
            );
        }
    }

    public function testEmptyCartSubmissionRedirects(): void
    {
        ProductFactory::new()->generalStore()->create(['quantity' => 10]);

        $crawler = $this->client->request('GET', '/kauppa');
        $form = $crawler->filter('form[name="cart"]')->form();
        $csrfToken = $form['cart[_token]']->getValue();

        // Submit with all products at quantity=0 (empty cart)
        $this->client->request('POST', '/kauppa', [
            'cart' => [
                '_token' => $csrfToken,
                'email' => 'test@example.com',
                'products' => [], // No products selected
            ],
        ]);

        // Should either redirect back to shop or show validation error
        $response = $this->client->getResponse();

        if ($response->isRedirect()) {
            $location = $response->headers->get('Location') ?? '';
            // If redirect, should NOT go to checkout (empty cart)
            $this->assertStringNotContainsString(
                '/checkout',
                $location,
                'Empty cart should not redirect to checkout',
            );
        } else {
            // Or show validation error on same page
            $this->assertResponseIsSuccessful('Should show validation error for empty cart');
        }
    }

    public function testMissingCsrfTokenRejected(): void
    {
        $product = ProductFactory::new()->generalStore()->create(['quantity' => 10]);

        // Submit without CSRF token
        $this->client->request('POST', '/kauppa', [
            'cart' => [
                // '_token' missing
                'email' => 'test@example.com',
                'products' => [
                    0 => [
                        'quantity' => 1,
                        'product' => (string) $product->getId(),
                    ],
                ],
            ],
        ]);

        // Should result in HTTP 422 or redirect back
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            [422, 302],
            'Missing CSRF token should result in validation error (422) or redirect (302)',
        );
    }

    public function testSoldOutProductHandling(): void
    {
        // Create sold out product
        $product = ProductFactory::new()->generalStore()->soldOut()->create([
            'nameFi' => 'Loppuunmyyty Tuote',
        ]);

        $this->client->request('GET', '/kauppa');

        $this->assertResponseIsSuccessful();

        // Sold out product should still be visible but with quantity=0
        $this->assertSame(0, $product->getMax(0), 'Sold out product should have max=0');
    }
}
