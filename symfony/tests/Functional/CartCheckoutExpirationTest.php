<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\CartFactory;
use App\Factory\CartItemFactory;
use App\Factory\CheckoutFactory;
use App\Factory\EventFactory;
use App\Factory\ProductFactory;
use App\Repository\CheckoutRepository;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * CartCheckoutExpirationTest.
 *
 * Tests cart and checkout expiration/cleanup logic:
 * - Checkout status transitions (open → completed/expired/processed)
 * - Ongoing checkouts reduce product availability
 * - Expired checkouts don't reduce product availability
 * - Repository methods for finding expired/unneeded checkouts
 *
 * Addresses GAP from todo.md line 34:
 * "Cart expiration and cleanup (abandoned carts, expired checkouts)"
 *
 * Status codes (from CheckoutFactory):
 *  - 0 = open (payment in progress)
 *  - 1 = completed (payment successful, awaiting ticket creation)
 *  - -1 = expired (session timeout, payment not completed)
 *  - 2 = processed (tickets created and sent)
 *
 * Roadmap alignment:
 * - CLAUDE.md §4: Factory-driven, structural assertions
 * - CLAUDE.md §8: Negative coverage policy (expired checkouts don't block)
 */
final class CartCheckoutExpirationTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testCheckoutCanBeCreatedInDifferentStatuses(): void
    {
        $cart = CartFactory::new()->create(['email' => 'status-test@example.com']);

        // Test each status: open, completed, expired, processed
        $openCheckout = CheckoutFactory::new()->open()->forCart($cart)->create();
        $this->assertSame(0, $openCheckout->getStatus(), 'Open checkout should have status=0');

        $completedCheckout = CheckoutFactory::new()->completed()->forCart($cart)->create();
        $this->assertSame(1, $completedCheckout->getStatus(), 'Completed checkout should have status=1');

        $expiredCheckout = CheckoutFactory::new()->expired()->forCart($cart)->create();
        $this->assertSame(-1, $expiredCheckout->getStatus(), 'Expired checkout should have status=-1');

        $processedCheckout = CheckoutFactory::new()->processed()->forCart($cart)->create();
        $this->assertSame(2, $processedCheckout->getStatus(), 'Processed checkout should have status=2');
    }

    public function testOngoingCheckoutsReduceProductAvailability(): void
    {
        $event = EventFactory::new()->create([
            'published' => true,
            'ticketsEnabled' => true,
            'ticketPresaleStart' => new \DateTimeImmutable('-1 day'),
            'ticketPresaleEnd' => new \DateTimeImmutable('+7 days'),
            'eventDate' => new \DateTimeImmutable('+14 days'),
            'url' => 'availability-test-'.uniqid('', true),
        ]);

        $product = ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create([
                'nameFi' => 'Limited Ticket',
                'quantity' => 10, // Total available
            ]);

        // Create ongoing checkout with 3 tickets
        $item1 = CartItemFactory::new()->forProduct($product)->withQuantity(3)->create();
        $cart1 = CartFactory::new()->withItems([$item1])->create();
        CheckoutFactory::new()->open()->forCart($cart1)->create();

        // Create another ongoing checkout with 2 tickets
        $item2 = CartItemFactory::new()->forProduct($product)->withQuantity(2)->create();
        $cart2 = CartFactory::new()->withItems([$item2])->create();
        CheckoutFactory::new()->open()->forCart($cart2)->create();

        // Verify ongoing checkouts are counted
        $checkoutRepo = static::getContainer()->get(CheckoutRepository::class);
        $ongoingQuantities = $checkoutRepo->findProductQuantitiesInOngoingCheckouts();

        $this->assertArrayHasKey(
            $product->getId(),
            $ongoingQuantities,
            'Product should be in ongoing checkouts',
        );
        $this->assertSame(
            5,
            $ongoingQuantities[$product->getId()],
            'Total ongoing checkout quantity should be 3 + 2 = 5',
        );
    }

    public function testExpiredCheckoutsDontReduceAvailability(): void
    {
        $event = EventFactory::new()->create([
            'published' => true,
            'ticketsEnabled' => true,
            'ticketPresaleStart' => new \DateTimeImmutable('-1 day'),
            'ticketPresaleEnd' => new \DateTimeImmutable('+7 days'),
            'eventDate' => new \DateTimeImmutable('+14 days'),
            'url' => 'expired-test-'.uniqid('', true),
        ]);

        $product = ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create([
                'nameFi' => 'Expirable Ticket',
                'quantity' => 10,
            ]);

        // Create expired checkout with 5 tickets
        $expiredItem = CartItemFactory::new()->forProduct($product)->withQuantity(5)->create();
        $expiredCart = CartFactory::new()->withItems([$expiredItem])->create();
        CheckoutFactory::new()->expired()->forCart($expiredCart)->create();

        // Create ongoing checkout with 2 tickets
        $ongoingItem = CartItemFactory::new()->forProduct($product)->withQuantity(2)->create();
        $ongoingCart = CartFactory::new()->withItems([$ongoingItem])->create();
        CheckoutFactory::new()->open()->forCart($ongoingCart)->create();

        // Verify only ongoing checkouts are counted (expired are ignored)
        $checkoutRepo = static::getContainer()->get(CheckoutRepository::class);
        $ongoingQuantities = $checkoutRepo->findProductQuantitiesInOngoingCheckouts();

        $this->assertSame(
            2,
            $ongoingQuantities[$product->getId()] ?? 0,
            'Only ongoing checkout quantity (2) should count, expired (5) should be ignored',
        );
    }

    public function testFindUnneededCheckoutsReturnsOnlyExpiredOnes(): void
    {
        $cart1 = CartFactory::new()->create(['email' => 'expired1@example.com']);
        $cart2 = CartFactory::new()->create(['email' => 'expired2@example.com']);
        $cart3 = CartFactory::new()->create(['email' => 'active@example.com']);

        // Create 2 expired checkouts
        $expired1 = CheckoutFactory::new()->expired()->forCart($cart1)->create();
        $expired2 = CheckoutFactory::new()->expired()->forCart($cart2)->create();

        // Create 1 ongoing checkout
        CheckoutFactory::new()->open()->forCart($cart3)->create();

        // Query for unneeded (expired) checkouts
        $checkoutRepo = static::getContainer()->get(CheckoutRepository::class);
        $unneeded = $checkoutRepo->findUnneededCheckouts();

        $this->assertIsArray($unneeded);
        $this->assertGreaterThanOrEqual(
            2,
            \count($unneeded),
            'Should find at least 2 expired checkouts',
        );

        // Verify all returned checkouts have status = -1 (expired)
        foreach ($unneeded as $checkout) {
            $this->assertSame(
                -1,
                $checkout->getStatus(),
                'findUnneededCheckouts should only return expired checkouts (status=-1)',
            );
        }

        // Verify our specific expired checkouts are in the result
        $expiredIds = array_map(static fn ($c) => $c->getId(), $unneeded);
        $this->assertContains($expired1->getId(), $expiredIds);
        $this->assertContains($expired2->getId(), $expiredIds);
    }

    public function testCheckoutStatusCanBeUpdatedToExpired(): void
    {
        $cart = CartFactory::new()->create(['email' => 'transition@example.com']);

        // Create checkout in open status
        $checkout = CheckoutFactory::new()->open()->forCart($cart)->create();
        $this->assertSame(0, $checkout->getStatus());

        // Simulate webhook marking it as expired (status -1)
        // Fetch fresh from DB to avoid Foundry proxy issues with Doctrine
        $checkoutRepo = static::getContainer()->get(CheckoutRepository::class);
        $checkoutFromDb = $checkoutRepo->find($checkout->getId());
        $this->assertNotNull($checkoutFromDb);
        $checkoutFromDb->setStatus(-1);
        $checkoutRepo->save($checkoutFromDb, true);

        // Reload from database and verify
        $this->em()->clear();
        $reloaded = $checkoutRepo->find($checkout->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(-1, $reloaded->getStatus(), 'Checkout status should be updated to expired (-1)');
    }

    public function testCompletedCheckoutsAreNotInOngoingList(): void
    {
        $event = EventFactory::new()->create([
            'published' => true,
            'ticketsEnabled' => true,
            'ticketPresaleStart' => new \DateTimeImmutable('-1 day'),
            'ticketPresaleEnd' => new \DateTimeImmutable('+7 days'),
            'eventDate' => new \DateTimeImmutable('+14 days'),
            'url' => 'completed-test-'.uniqid('', true),
        ]);

        $product = ProductFactory::new()->ticket()->forEvent($event)->create();

        // Create completed checkout (payment successful)
        $completedItem = CartItemFactory::new()->forProduct($product)->withQuantity(3)->create();
        $completedCart = CartFactory::new()->withItems([$completedItem])->create();
        CheckoutFactory::new()->completed()->forCart($completedCart)->create();

        // Verify completed checkouts are NOT in ongoing list
        $checkoutRepo = static::getContainer()->get(CheckoutRepository::class);
        $ongoing = $checkoutRepo->findOngoingCheckouts();

        // Filter to check if our completed checkout is in the ongoing list
        $hasCompleted = false;
        foreach ($ongoing as $checkout) {
            if ($checkout->getCart()->getEmail() === $completedCart->getEmail()) {
                $hasCompleted = true;
                break;
            }
        }

        $this->assertFalse(
            $hasCompleted,
            'Completed checkouts (status=1) should NOT be in ongoing checkouts list',
        );
    }

    public function testProcessedCheckoutsAreNotInOngoingList(): void
    {
        $event = EventFactory::new()->create([
            'published' => true,
            'ticketsEnabled' => true,
            'ticketPresaleStart' => new \DateTimeImmutable('-1 day'),
            'ticketPresaleEnd' => new \DateTimeImmutable('+7 days'),
            'eventDate' => new \DateTimeImmutable('+14 days'),
            'url' => 'processed-test-'.uniqid('', true),
        ]);

        $product = ProductFactory::new()->ticket()->forEvent($event)->create();

        // Create processed checkout (tickets already sent)
        $processedItem = CartItemFactory::new()->forProduct($product)->withQuantity(2)->create();
        $processedCart = CartFactory::new()->withItems([$processedItem])->create();
        CheckoutFactory::new()->processed()->forCart($processedCart)->create();

        // Verify processed checkouts are NOT in ongoing list
        $checkoutRepo = static::getContainer()->get(CheckoutRepository::class);
        $ongoing = $checkoutRepo->findOngoingCheckouts();

        $hasProcessed = false;
        foreach ($ongoing as $checkout) {
            if ($checkout->getCart()->getEmail() === $processedCart->getEmail()) {
                $hasProcessed = true;
                break;
            }
        }

        $this->assertFalse(
            $hasProcessed,
            'Processed checkouts (status=2) should NOT be in ongoing checkouts list',
        );
    }
}
