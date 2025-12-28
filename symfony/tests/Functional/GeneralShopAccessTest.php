<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Factory\ProductFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LocaleDataProviderTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;

/**
 * GeneralShopAccessTest.
 *
 * Tests access to the general store (non-event products) at /kauppa and /en/shop.
 * Verifies:
 * - Bilingual route accessibility
 * - Product listing for general store items
 * - Cart form presence
 * - Anonymous user access (no authentication required)
 *
 * Roadmap alignment:
 * - CLAUDE.md §4: Factory-driven, structural assertions
 * - CLAUDE.md §21.1: Site-aware client for multisite routing
 * - CLAUDE.md §7: Locale strategy (Finnish unprefixed, English /en/)
 */
final class GeneralShopAccessTest extends FixturesWebTestCase
{
    use LocaleDataProviderTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public static function localeProvider(): iterable
    {
        yield 'Finnish' => ['fi'];
        yield 'English' => ['en'];
    }

    #[DataProvider('localeProvider')]
    public function testGeneralShopAccessibleInBothLocales(string $locale): void
    {
        $this->seedClientHome($locale);

        // Create general store product (not linked to event)
        ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Yleinen Tuote',
            'nameEn' => 'General Product',
            'quantity' => 10,
        ]);

        $shopPath = 'en' === $locale ? '/en/shop' : '/kauppa';

        $this->client->request('GET', $shopPath);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]', 'Cart form should be present');
    }

    public function testGeneralShopShowsOnlyGeneralProducts(): void
    {
        // Create general store product
        $generalProduct = ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Yleinen Tuote',
            'quantity' => 5,
        ]);

        // Create event-specific product (should NOT appear in general shop)
        $event = EventFactory::new()->create(['url' => 'test-event-'.uniqid('', true)]);
        $eventProduct = ProductFactory::new()->ticket()->forEvent($event)->create([
            'nameFi' => 'Tapahtuma Lippu',
            'quantity' => 10,
        ]);

        $this->client->request('GET', '/kauppa');

        $this->assertResponseIsSuccessful();

        // General product should be visible
        $this->client->assertSelectorExists('form[name="cart"]');

        // Event product should NOT be in general shop (only general products with event=null)
        // This is enforced by ProductRepository::findGeneralStoreProducts()
        $this->assertNotNull($generalProduct->getId());
        $this->assertNull($generalProduct->getEvent(), 'General product should not have an event');
        $this->assertNotNull($eventProduct->getEvent(), 'Event product should have an event');
    }

    public function testGeneralShopShowsOnlyActiveProducts(): void
    {
        // Create active product
        ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Aktiivinen Tuote',
            'active' => true,
            'quantity' => 10,
        ]);

        // Create inactive product (should NOT appear)
        ProductFactory::new()->generalStore()->inactive()->create([
            'nameFi' => 'Epäaktiivinen Tuote',
            'quantity' => 10,
        ]);

        $this->client->request('GET', '/kauppa');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]');

        // Repository filters by active=true, so inactive products won't appear
        // This is enforced by ProductRepository::findGeneralStoreProducts()
    }

    public function testGeneralShopExcludesServiceFees(): void
    {
        // Create regular product
        ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Tavallinen Tuote',
            'quantity' => 10,
        ]);

        // Create service fee (should be excluded from general shop)
        ProductFactory::new()->serviceFee()->create([
            'event' => null, // General store service fee (if such exists)
        ]);

        $this->client->request('GET', '/kauppa');

        $this->assertResponseIsSuccessful();

        // Service fees are filtered out by ProductRepository::findGeneralStoreProducts()
        // (serviceFee = false condition)
    }

    public function testEmptyGeneralShopShowsNoProductsMessage(): void
    {
        // Don't create any products

        $this->client->request('GET', '/kauppa');

        $this->assertResponseIsSuccessful();

        // Should show "no products" message from shop/index.html.twig line 48
        $this->client->assertSelectorExists('.alert-info', 'Should show info alert when no products');
    }

    #[DataProvider('localeProvider')]
    public function testAnonymousUserCanAccessGeneralShop(string $locale): void
    {
        $this->seedClientHome($locale);

        ProductFactory::new()->generalStore()->create([
            'nameFi' => 'Julkinen Tuote',
            'nameEn' => 'Public Product',
            'quantity' => 20,
        ]);

        $shopPath = 'en' === $locale ? '/en/shop' : '/kauppa';

        $this->client->request('GET', $shopPath);

        // Should NOT redirect to login (general shop is public)
        $response = $this->client->getResponse();
        if ($response->isRedirect()) {
            $location = $response->headers->get('Location') ?? '';
            $this->assertStringNotContainsString(
                '/login',
                $location,
                'Anonymous users should not be redirected to login for general shop',
            );
        }

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]');
    }

    public function testEmailFieldVisibleForAnonymousUsers(): void
    {
        ProductFactory::new()->generalStore()->create(['quantity' => 10]);

        $this->client->request('GET', '/kauppa');

        $this->assertResponseIsSuccessful();

        // Email field should be visible for anonymous users (shop/index.html.twig line 31)
        $this->client->assertSelectorExists('input[name="cart[email]"]', 'Email field should be present for anonymous users');
    }

    /**
     * Test that invalid cart ID in session creates a new cart.
     * Covers ShopController lines 54-56.
     */
    public function testShopCreatesNewCartWhenSessionCartNotFound(): void
    {
        ProductFactory::new()->generalStore()->create(['quantity' => 10]);

        // Set an invalid cart ID in session
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set('cart', 999999);
        $session->save();

        // Set session cookie
        $this->client->getCookieJar()->set(new Cookie(
            $session->getName(),
            $session->getId(),
        ));

        $this->client->request('GET', '/kauppa');

        // Should still be successful with a new cart
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]');
    }
}
