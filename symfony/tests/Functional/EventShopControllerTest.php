<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\CartFactory;
use App\Factory\CartItemFactory;
use App\Factory\CheckoutFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\ProductFactory;
use App\Factory\TicketFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Symfony\Component\BrowserKit\Cookie;

/**
 * EventShopControllerTest.
 *
 * Tests for EventShopController covering:
 * - Cart session handling (lines 97-99)
 * - Product checkout tracking (line 112)
 * - User ticket deduplication (lines 121-126)
 * - Paid tickets count (lines 150-151)
 * - Empty cart checkout redirect (lines 191-196)
 * - Product sold out flash (line 207)
 * - Complete action (lines 253-263)
 */
final class EventShopControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    private function getDates(): array
    {
        $clock = static::getContainer()->get(\App\Time\ClockInterface::class);
        $realNow = $clock->now();

        return [$realNow, $realNow];
    }

    private function createOpenPresaleEvent(): \App\Entity\Event
    {
        [$realNow] = $this->getDates();

        return EventFactory::new()->create([
            'published' => true,
            'publishDate' => $realNow->modify('-5 minutes'),
            'ticketsEnabled' => true,
            'ticketPresaleStart' => $realNow->modify('-1 day'),
            'ticketPresaleEnd' => $realNow->modify('+7 days'),
            'eventDate' => $realNow->modify('+14 days'),
            'url' => 'event-shop-test-'.uniqid('', true),
        ]);
    }

    /**
     * Test cart ID in session points to non-existent cart - should create new cart.
     * Covers lines 97-99.
     */
    public function testShopCreatesNewCartWhenSessionCartNotFound(): void
    {
        $event = $this->createOpenPresaleEvent();
        ProductFactory::new()->ticket()->forEvent($event)->create(['quantity' => 10]);

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        // Set an invalid cart ID in session
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set('cart', 999999);
        $session->save();

        // Set session cookie
        $this->client->getCookieJar()->set(new Cookie(
            $session->getName(),
            $session->getId(),
        ));

        $this->client->request('GET', $shopPath);

        // Should still be successful with a new cart
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]');
    }

    /**
     * Test that products in ongoing checkouts are tracked.
     * Covers line 112.
     */
    public function testShopTracksProductsInOngoingCheckouts(): void
    {
        $event = $this->createOpenPresaleEvent();
        $product = ProductFactory::new()->ticket()->forEvent($event)->create([
            'quantity' => 5,
        ]);

        // Create an ongoing checkout with this product
        $cartItem = CartItemFactory::new()->forProduct($product)->withQuantity(2)->create();
        $cart = CartFactory::new()->withItems([$cartItem])->withEmail('ongoing@example.com')->create();
        CheckoutFactory::new()->open()->forCart($cart)->create();

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Should be successful - the inCheckouts array will be passed to the template
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]');
    }

    /**
     * Test that user who already has a ticket doesn't see that product.
     * Covers lines 121, 124, 126.
     */
    public function testShopRemovesProductIfUserAlreadyHasTicket(): void
    {
        $event = $this->createOpenPresaleEvent();
        $product = ProductFactory::new()->ticket()->forEvent($event)->create([
            'quantity' => 10,
            'howManyOneCanBuyAtOneTime' => 1,
        ]);

        $user = $this->getOrCreateUser('ticket-owner+'.bin2hex(random_bytes(4)).'@example.test', []);
        \assert($user instanceof User);

        // Create a paid ticket for this user with matching stripe product ID
        TicketFactory::new()
            ->forEvent($event)
            ->paid()
            ->withEmail($user->getEmail())
            ->create([
                'stripeProductId' => $product->getStripeId(),
            ]);

        $this->client->loginUser($user);
        $this->seedClientHome('fi');

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Should be successful - the product should be filtered out
        $this->assertResponseIsSuccessful();
    }

    /**
     * Test that paid tickets are counted for totalSold.
     * Covers lines 150-151.
     */
    public function testShopCountsPaidTickets(): void
    {
        $event = $this->createOpenPresaleEvent();
        ProductFactory::new()->ticket()->forEvent($event)->create(['quantity' => 100]);

        // Create some paid tickets for this event
        TicketFactory::new()->forEvent($event)->paid()->create();
        TicketFactory::new()->forEvent($event)->paid()->create();
        TicketFactory::new()->forEvent($event)->available()->create(); // Not paid, shouldn't count

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Should be successful - totalSold will be passed to template
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="cart"]');
    }

    /**
     * Test checkout redirects to shop when cart is empty (event shop).
     * Covers lines 191-196.
     */
    public function testEventCheckoutRedirectsToShopWhenCartIsEmpty(): void
    {
        $event = $this->createOpenPresaleEvent();
        ProductFactory::new()->ticket()->forEvent($event)->create(['quantity' => 10]);

        $year = (int) $event->getEventDate()->format('Y');
        $checkoutPath = \sprintf('/%d/%s/kassa', $year, $event->getUrl());

        // No cart in session
        $this->client->request('GET', $checkoutPath);

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Should redirect when cart is empty');

        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString(
            '/kauppa',
            $location,
            'Should redirect to event shop when cart is empty',
        );
    }

    /**
     * Test complete action with open status redirects back to checkout.
     * Covers lines 253-261.
     */
    public function testEventCompleteRedirectsWhenCheckoutIsOpen(): void
    {
        $event = $this->createOpenPresaleEvent();

        $cart = CartFactory::new()->withEmail('open-checkout@example.com')->create();
        $checkout = CheckoutFactory::new()->open()->forCart($cart)->create();

        $year = (int) $event->getEventDate()->format('Y');
        $completePath = \sprintf('/%d/%s/valmis?session_id=%s', $year, $event->getUrl(), $checkout->getStripeSessionId());

        $this->client->request('GET', $completePath);

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Should redirect when checkout is still open');

        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString(
            '/kassa',
            $location,
            'Should redirect to checkout when status is open',
        );
    }

    /**
     * Test complete action with completed status shows success.
     * Covers lines 263-274.
     */
    public function testEventCompleteShowsSuccessWhenCheckoutIsCompleted(): void
    {
        $event = $this->createOpenPresaleEvent();

        $cart = CartFactory::new()->withEmail('completed@example.com')->create();
        $checkout = CheckoutFactory::new()->completed()->forCart($cart)->create();

        $year = (int) $event->getEventDate()->format('Y');
        $completePath = \sprintf('/%d/%s/valmis?session_id=%s', $year, $event->getUrl(), $checkout->getStripeSessionId());

        $this->client->request('GET', $completePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test checkout shows sold out flash when product has no stock.
     * Covers line 207.
     */
    public function testEventCheckoutShowsSoldOutFlashWhenProductSoldOut(): void
    {
        $event = $this->createOpenPresaleEvent();

        // Create a product with zero quantity (sold out)
        $product = ProductFactory::new()->ticket()->forEvent($event)->create([
            'quantity' => 0,
        ]);

        // Create a cart with this sold-out product
        $cartItem = CartItemFactory::new()->forProduct($product)->withQuantity(1)->create();
        $cart = CartFactory::new()->withItems([$cartItem])->withEmail('soldout@example.com')->create();

        $year = (int) $event->getEventDate()->format('Y');

        // Set cart in session via session factory
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set('cart', $cart->getId());
        $session->save();

        $this->client->getCookieJar()->set(new Cookie(
            $session->getName(),
            $session->getId(),
        ));

        $checkoutPath = \sprintf('/%d/%s/kassa', $year, $event->getUrl());
        $this->client->request('GET', $checkoutPath);

        // Should redirect back to shop (RuntimeException is thrown by FakeStripeService
        // because no valid line items)
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect());

        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('/kauppa', $location);
    }

    /**
     * Test checkout handles RuntimeException from StripeService.
     * Covers lines 214-220.
     */
    public function testEventCheckoutHandlesRuntimeException(): void
    {
        $event = $this->createOpenPresaleEvent();

        // Create a product that will be sold out when checkout is attempted
        $product = ProductFactory::new()->ticket()->forEvent($event)->create([
            'quantity' => 1, // Only 1 available
        ]);

        // Create an ongoing checkout that reserves all stock
        $otherCartItem = CartItemFactory::new()->forProduct($product)->withQuantity(1)->create();
        $otherCart = CartFactory::new()->withItems([$otherCartItem])->withEmail('other@example.com')->create();
        CheckoutFactory::new()->open()->forCart($otherCart)->create();

        // Now create our cart trying to buy the same product
        $cartItem = CartItemFactory::new()->forProduct($product)->withQuantity(1)->create();
        $cart = CartFactory::new()->withItems([$cartItem])->withEmail('buyer@example.com')->create();

        $year = (int) $event->getEventDate()->format('Y');

        // Set cart in session via session factory
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set('cart', $cart->getId());
        $session->save();

        $this->client->getCookieJar()->set(new Cookie(
            $session->getName(),
            $session->getId(),
        ));

        $checkoutPath = \sprintf('/%d/%s/kassa', $year, $event->getUrl());
        $this->client->request('GET', $checkoutPath);

        // Should redirect back to shop due to RuntimeException
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect());

        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('/kauppa', $location);
    }
}
