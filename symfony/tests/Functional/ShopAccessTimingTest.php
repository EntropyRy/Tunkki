<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Factory\ProductFactory;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * ShopAccessTimingTest.
 *
 * Tests shop access control based on ticket presale timing windows:
 * - Presale not started yet (403)
 * - Presale ended (403)
 * - Event in the past (403)
 * - Valid presale window (200)
 *
 * Addresses GAP from todo.md line 38:
 * "Shop closed scenarios (presale not started, presale ended, event past)"
 *
 * Roadmap alignment:
 * - CLAUDE.md §4: Factory-driven, structural assertions
 * - CLAUDE.md §21.1: Site-aware client for multisite routing
 * - CLAUDE.md §8: Negative coverage policy (access boundary tests)
 */
final class ShopAccessTimingTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    /**
     * Helper to create dates for event creation.
     * Returns [realNow, testNow] where:
     * - realNow: For Entity methods that use real system time (like ticketPresaleEnabled)
     * - testNow: For domain services that use ClockInterface (like EventPublicationDecider).
     */
    private function getDates(): array
    {
        $realNow = new \DateTimeImmutable();
        $clock = static::getContainer()->get(\App\Time\ClockInterface::class);
        $testNow = $clock->now();

        return [$realNow, $testNow];
    }

    public function testShopInaccessibleWhenPresaleNotStarted(): void
    {
        [$realNow, $testNow] = $this->getDates();

        $event = EventFactory::new()->create([
            'published' => true,
            'publishDate' => $testNow->modify('-5 minutes'),
            'ticketsEnabled' => true,
            'ticketPresaleStart' => $realNow->modify('+2 days'), // Presale starts in future
            'ticketPresaleEnd' => $realNow->modify('+7 days'),
            'eventDate' => $realNow->modify('+14 days'),
            'nakkiRequiredForTicketReservation' => false,
            'url' => 'presale-not-started-'.uniqid('', true),
        ]);

        ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'Future Ticket']);

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Anonymous users get 302 redirect to login when access is denied
        $response = $this->client->getResponse();
        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Anonymous users should be redirected when presale has not started',
        );
        $this->assertSame(
            'http://localhost/login',
            $response->headers->get('Location'),
            'Should redirect to /login',
        );
    }

    public function testShopInaccessibleWhenPresaleEnded(): void
    {
        [$realNow, $testNow] = $this->getDates();

        $event = EventFactory::new()->create([
            'published' => true,
            'publishDate' => $testNow->modify('-10 days'),
            'ticketsEnabled' => true,
            'ticketPresaleStart' => $realNow->modify('-7 days'),
            'ticketPresaleEnd' => $realNow->modify('-1 day'), // Presale ended yesterday
            'eventDate' => $realNow->modify('+7 days'), // Event still in future
            'nakkiRequiredForTicketReservation' => false,
            'url' => 'presale-ended-'.uniqid('', true),
        ]);

        ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'Expired Presale Ticket']);

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Anonymous users get 302 redirect to login when access is denied
        $response = $this->client->getResponse();
        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Anonymous users should be redirected when presale has ended',
        );
        $this->assertSame(
            'http://localhost/login',
            $response->headers->get('Location'),
            'Should redirect to /login',
        );
    }

    public function testShopInaccessibleWhenEventInPast(): void
    {
        [$realNow, $testNow] = $this->getDates();

        $event = EventFactory::new()->create([
            'published' => true,
            'publishDate' => $testNow->modify('-30 days'),
            'ticketsEnabled' => true,
            'ticketPresaleStart' => $realNow->modify('-20 days'),
            'ticketPresaleEnd' => $realNow->modify('-10 days'),
            'eventDate' => $realNow->modify('-5 days'), // Event already happened
            'nakkiRequiredForTicketReservation' => false,
            'url' => 'past-event-'.uniqid('', true),
        ]);

        ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'Past Event Ticket']);

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Anonymous users get 302 redirect to login when access is denied
        $response = $this->client->getResponse();
        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Anonymous users should be redirected when event is in the past',
        );
        $this->assertSame(
            'http://localhost/login',
            $response->headers->get('Location'),
            'Should redirect to /login',
        );
    }

    public function testShopAccessibleDuringValidPresaleWindow(): void
    {
        [$realNow, $testNow] = $this->getDates();

        $event = EventFactory::new()->create([
            'published' => true,
            'publishDate' => $testNow->modify('-5 minutes'),
            'ticketsEnabled' => true,
            'ticketPresaleStart' => $realNow->modify('-1 day'), // Started yesterday
            'ticketPresaleEnd' => $realNow->modify('+7 days'), // Ends in a week
            'eventDate' => $realNow->modify('+14 days'), // Event in 2 weeks
            'nakkiRequiredForTicketReservation' => false,
            'url' => 'valid-presale-'.uniqid('', true),
        ]);

        ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'Valid Presale Ticket']);

        $year = (int) $event->getEventDate()->format('Y');
        $shopPath = \sprintf('/%d/%s/kauppa', $year, $event->getUrl());

        $this->client->request('GET', $shopPath);

        // Should succeed during valid presale window
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists(
            'form[name="cart"]',
            'Cart form should be present during valid presale',
        );
    }
}
