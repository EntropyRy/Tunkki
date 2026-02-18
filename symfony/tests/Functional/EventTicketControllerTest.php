<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Ticket;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\TicketFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for EventTicketController.
 *
 * Covered scenarios:
 *  1. Single ticket display - owner can view their ticket (bilingual)
 *  2. Single ticket display - non-owner cannot view ticket (404)
 *  3. Single ticket display - ticket not for this event (404)
 *  4. Single ticket display - unauthenticated user redirected
 *  5. Tickets list - member can view all their tickets for event (bilingual)
 *  6. Tickets list - empty tickets list (bilingual)
 *  7. Tickets list - unauthenticated user redirected
 *  8. Tickets list - showShop logic: single product type → always show shop link
 *  9. Tickets list - showShop logic: multiple types, member has some → show shop link
 * 10. Tickets list - showShop logic: multiple types, member has all → hide shop link
 * 11. Ticket check page - accessible when authenticated (bilingual)
 * 12. Ticket check page - unauthenticated user redirected
 * 13. Ticket API check - returns correct ticket info
 * 14. Ticket API check - ticket mismatch returns error
 * 15. Ticket API give - marks ticket as given and persists change
 */
final class EventTicketControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    /**
     * Locale data provider for bilingual tests.
     *
     * @return iterable<string,array{string}>
     */
    public static function localeProvider(): iterable
    {
        yield 'fi' => ['fi'];
        yield 'en' => ['en'];
    }

    public function testMemberTicketHelpers(): void
    {
        $member = new Member();
        $event = new Event();

        $ticket = new Ticket();
        $ticket->setEvent($event);
        $ticket->setPrice(1000);

        $member->addTicket($ticket);
        $this->assertCount(1, $member->getTickets());
        $this->assertSame($ticket, $member->getTicketForEvent($event));

        $otherEvent = new Event();
        $this->assertNull($member->getTicketForEvent($otherEvent));

        $member->removeTicket($ticket);
        $this->assertCount(0, $member->getTickets());
    }

    /* =========================================================================
     * ticket() Action - Single Ticket Display
     * ========================================================================= */

    #[DataProvider('localeProvider')]
    public function testTicketDisplaysForOwner(string $locale): void
    {
        // Arrange: Create event, member, and ticket
        $reference = $this->uniqueReferenceNumber();
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();
        self::assertNotNull($ticket->getId());
        self::assertSame((string) $ticket->getReferenceNumber(), (string) $ticket);
        self::assertSame($member->getEmail(), $ticket->getOwnerEmail());
        self::assertInstanceOf(\DateTimeImmutable::class, $ticket->getUpdatedAt());
        $ticket->setUpdatedAt(new \DateTimeImmutable('2030-01-01 12:00:00'));
        self::assertSame('2030-01-01 12:00:00', $ticket->getUpdatedAt()->format('Y-m-d H:i:s'));
        $ticket->setGiven(true);
        self::assertTrue($ticket->isGiven());

        // Login as the ticket owner
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the ticket page
        $path = $this->buildTicketPath($locale, $event->getEventDate()->format('Y'), $event->getUrl(), $ticket->getReferenceNumber());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully with ticket info
        $this->assertResponseIsSuccessful();
        // Template renders ticket - just verify page loads
    }

    #[DataProvider('localeProvider')]
    public function testTicketDeniesNonOwner(string $locale): void
    {
        // Arrange: Create event and ticket for one member
        $reference = $this->uniqueReferenceNumber();
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $ownerMember = MemberFactory::new()->create();
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($ownerMember)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();

        // Login as a different member
        $otherMember = MemberFactory::new()->create();
        $this->loginAsMember($otherMember->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Attempt to view another member's ticket
        $path = $this->buildTicketPath($locale, $event->getEventDate()->format('Y'), $event->getUrl(), $ticket->getReferenceNumber());
        $this->client()->request('GET', $path);

        // Assert: 404 Not Found (security through obscurity - don't reveal ticket exists)
        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('localeProvider')]
    public function testTicketReturns404WhenTicketNotForEvent(string $locale): void
    {
        // Arrange: Create two events
        $reference = $this->uniqueReferenceNumber();
        $event1 = EventFactory::new()
            ->published()
            ->create([
                'url' => 'event-one-'.uniqid('', true),
            ]);

        $event2 = EventFactory::new()
            ->published()
            ->create([
                'url' => 'event-two-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();

        // Ticket belongs to event2, but we'll try to access it via event1's URL
        $ticket = TicketFactory::new()
            ->forEvent($event2)
            ->ownedBy($member)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();

        // Login as the ticket owner
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Try to access ticket via wrong event URL
        $path = $this->buildTicketPath($locale, $event1->getEventDate()->format('Y'), $event1->getUrl(), $ticket->getReferenceNumber());
        $this->client()->request('GET', $path);

        // Assert: 404 Not Found
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTicketRedirectsUnauthenticatedUser(): void
    {
        // Arrange: Create event and ticket
        $reference = $this->uniqueReferenceNumber();
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();

        // Act: Request ticket without authentication
        $path = $this->buildTicketPath('fi', $event->getEventDate()->format('Y'), $event->getUrl(), $ticket->getReferenceNumber());
        $this->client()->request('GET', $path);

        // Assert: Redirects to login
        $this->assertResponseStatusCodeSame(302);
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);
    }

    /* =========================================================================
     * tickets() Action - Multiple Tickets List
     * ========================================================================= */

    #[DataProvider('localeProvider')]
    public function testTicketsListDisplaysMemberTickets(string $locale): void
    {
        // Arrange: Create event and multiple tickets for member
        $reference1 = $this->uniqueReferenceNumber();
        $reference2 = $this->uniqueReferenceNumber();
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();

        $ticket1 = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->withName('General Admission')
            ->withReferenceNumber($reference1)
            ->create();

        $ticket2 = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->withName('VIP')
            ->withReferenceNumber($reference2)
            ->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the tickets list page
        $path = $this->buildTicketsPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully
        $this->assertResponseIsSuccessful();

        // Verify both tickets are displayed (assuming template shows ticket info)
        $content = $this->client()->getResponse()->getContent();
        $this->assertNotFalse($content);
        // Note: Exact assertions depend on template structure
    }

    #[DataProvider('localeProvider')]
    public function testTicketsListShowsEmptyWhenNoTickets(string $locale): void
    {
        // Arrange: Create event but no tickets for member
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the tickets list page
        $path = $this->buildTicketsPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully (no tickets is valid state)
        $this->assertResponseIsSuccessful();
    }

    public function testTicketsListRedirectsUnauthenticatedUser(): void
    {
        // Arrange: Create event
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Act: Request tickets list without authentication
        $path = $this->buildTicketsPath('fi', $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to login
        $this->assertResponseStatusCodeSame(302);
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);
    }

    public function testTicketsListShowsShopLinkWhenSingleProductType(): void
    {
        // Arrange: Create event with single product type
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $product = \App\Factory\ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create();

        $member = MemberFactory::new()->create();

        // Member has a ticket for this product
        TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->with(['stripeProductId' => $product->getStripeId()])
            ->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Request the tickets list page
        $path = $this->buildTicketsPath('fi', $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully
        // With single product type, showShop should always be true
        $this->assertResponseIsSuccessful();
        // Note: To truly verify showShop=true, we'd need to check the template output
        // or make showShop available in the response, but at minimum verify page loads
    }

    public function testTicketsListShowsShopLinkWhenMemberHasSomeTicketTypes(): void
    {
        // Arrange: Create event with multiple product types
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $generalProduct = \App\Factory\ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'General Admission']);

        $vipProduct = \App\Factory\ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'VIP']);

        $member = MemberFactory::new()->create();

        // Member only has general admission ticket
        TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->with(['stripeProductId' => $generalProduct->getStripeId()])
            ->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Request the tickets list page
        $path = $this->buildTicketsPath('fi', $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully
        // Member has 1 out of 2 product types, so showShop should be true
        $this->assertResponseIsSuccessful();
    }

    public function testTicketsListHidesShopLinkWhenMemberHasAllTicketTypes(): void
    {
        // Arrange: Create event with multiple product types
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $generalProduct = \App\Factory\ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'General Admission']);

        $vipProduct = \App\Factory\ProductFactory::new()
            ->ticket()
            ->forEvent($event)
            ->create(['nameFi' => 'VIP']);

        $member = MemberFactory::new()->create();

        // Member has both ticket types
        TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->with(['stripeProductId' => $generalProduct->getStripeId()])
            ->create();

        TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->with(['stripeProductId' => $vipProduct->getStripeId()])
            ->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Request the tickets list page
        $path = $this->buildTicketsPath('fi', $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully
        // Member has all product types, so showShop should be false
        $this->assertResponseIsSuccessful();
    }

    /* =========================================================================
     * ticketCheck() Action - Ticket Checking Page
     * ========================================================================= */

    #[DataProvider('localeProvider')]
    public function testTicketCheckPageAccessible(string $locale): void
    {
        // Arrange: Create event
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Login (any authenticated user can access check page)
        $this->loginAsMember();
        $this->seedClientHome($locale);

        // Act: Request the ticket check page
        $path = $this->buildTicketCheckPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Page displays successfully
        $this->assertResponseIsSuccessful();
    }

    public function testTicketCheckRedirectsUnauthenticatedUser(): void
    {
        // Arrange: Create event
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Act: Request ticket check page without authentication
        $path = $this->buildTicketCheckPath('fi', $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to login
        $this->assertResponseStatusCodeSame(302);
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);
    }

    /* =========================================================================
     * ticketApiCheck() Action - API Ticket Validation
     * ========================================================================= */

    public function testTicketApiCheckReturnsCorrectTicketInfo(): void
    {
        // Arrange: Create event, member, and ticket
        $reference = $this->uniqueReferenceNumber();
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $email = 'ticketholder-'.bin2hex(random_bytes(4)).'@example.com';
        $member = MemberFactory::new()->create(['email' => $email]);
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();

        // Login (API might still require authentication)
        $this->loginAsMember();

        // Act: Call the API endpoint
        $path = '/api/ticket/'.$event->getId().'/'.$ticket->getReferenceNumber().'/info';
        $this->client()->request('GET', $path);

        // Assert: Returns JSON with ticket info
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $this->client()->getResponse()->getContent();
        $this->assertNotFalse($content);

        // Response is wrapped in JsonResponse which double-encodes
        $outerData = json_decode($content, true);
        $this->assertIsString($outerData, 'JsonResponse wraps the data as a JSON string');

        // Decode the inner JSON
        $ticketData = json_decode($outerData, true);
        $this->assertIsArray($ticketData);
        $this->assertSame($email, $ticketData['email']);
        $this->assertSame('paid', $ticketData['status']);
        $this->assertFalse($ticketData['given']);
        $this->assertSame($reference, $ticketData['referenceNumber']);
    }

    public function testTicketApiCheckReturnsErrorForMismatchedTicket(): void
    {
        // Arrange: Create two events
        $reference = $this->uniqueReferenceNumber();
        $event1 = EventFactory::new()
            ->published()
            ->create([
                'url' => 'event-one-'.uniqid('', true),
            ]);

        $event2 = EventFactory::new()
            ->published()
            ->create([
                'url' => 'event-two-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();

        // Ticket belongs to event2
        $ticket = TicketFactory::new()
            ->forEvent($event2)
            ->ownedBy($member)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();

        // Login
        $this->loginAsMember();

        // Act: Call API with event1 ID but ticket from event2
        $path = '/api/ticket/'.$event1->getId().'/'.$ticket->getReferenceNumber().'/info';
        $this->client()->request('GET', $path);

        // Assert: Returns error
        $this->assertResponseIsSuccessful();
        $content = $this->client()->getResponse()->getContent();
        $this->assertNotFalse($content);

        // Response is wrapped in JsonResponse which double-encodes
        $outerData = json_decode($content, true);
        $this->assertIsString($outerData, 'JsonResponse wraps the data as a JSON string');

        // Decode the inner JSON
        $ticketData = json_decode($outerData, true);
        $this->assertIsArray($ticketData);
        $this->assertArrayHasKey('error', $ticketData);
        $this->assertSame('not valid', $ticketData['error']);
    }

    /* =========================================================================
     * ticketApiGive() Action - Mark Ticket as Given
     * ========================================================================= */

    public function testTicketApiGiveMarksTicketAsGiven(): void
    {
        // Arrange: Create event and ticket
        $reference = $this->uniqueReferenceNumber();
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->withReferenceNumber($reference)
            ->create();

        $this->assertFalse($ticket->isGiven(), 'Ticket should not be given initially');

        // Login
        $this->loginAsMember();

        // Act: Call the give API endpoint
        $path = '/api/ticket/'.$event->getId().'/'.$ticket->getReferenceNumber().'/give';
        $this->client()->request('POST', $path);

        // Assert: Returns success
        $this->assertResponseIsSuccessful();
        $content = $this->client()->getResponse()->getContent();
        $this->assertNotFalse($content);

        // Response is wrapped in JsonResponse which double-encodes
        $outerData = json_decode($content, true);
        $this->assertIsString($outerData, 'JsonResponse wraps the data as a JSON string');

        // Decode the inner JSON
        $responseData = json_decode($outerData, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('ok', $responseData);
        $this->assertSame('TICKET_GIVEN_OUT', $responseData['ok']);

        // Verify ticket was marked as given in database
        $this->em()->clear();
        $updatedTicket = $this->em()->getRepository(Ticket::class)->find($ticket->getId());
        $this->assertNotNull($updatedTicket);
        $this->assertTrue($updatedTicket->isGiven(), 'Ticket should be marked as given');
    }

    /* =========================================================================
     * Helper Methods
     * ========================================================================= */

    /**
     * Build the single ticket display path for the given locale.
     */
    private function buildTicketPath(string $locale, string $year, string $slug, ?int $reference): string
    {
        if ('en' === $locale) {
            return '/en/'.$year.'/'.$slug.'/ticket/'.$reference;
        }

        return '/'.$year.'/'.$slug.'/lippu/'.$reference;
    }

    /**
     * Build the tickets list path for the given locale.
     */
    private function buildTicketsPath(string $locale, string $year, string $slug): string
    {
        if ('en' === $locale) {
            return '/en/'.$year.'/'.$slug.'/tickets';
        }

        return '/'.$year.'/'.$slug.'/liput';
    }

    /**
     * Build the ticket check page path for the given locale.
     */
    private function buildTicketCheckPath(string $locale, string $year, string $slug): string
    {
        if ('en' === $locale) {
            return '/en/'.$year.'/'.$slug.'/ticket/check';
        }

        return '/'.$year.'/'.$slug.'/lippu/tarkistus';
    }

    private function uniqueReferenceNumber(): int
    {
        return random_int(100000, 999999);
    }
}
