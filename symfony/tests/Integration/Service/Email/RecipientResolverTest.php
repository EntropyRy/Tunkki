<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Email;

use App\Enum\EmailPurpose;
use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\ProductFactory;
use App\Factory\RSVPFactory;
use App\Factory\TicketFactory;
use App\Service\Email\RecipientResolver;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * Integration tests for RecipientResolver using real DB and factories.
 *
 * @covers \App\Service\Email\RecipientResolver
 */
final class RecipientResolverTest extends FixturesWebTestCase
{
    private RecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = static::getContainer()->get(RecipientResolver::class);
    }

    public function testResolveRsvpWithRealEvent(): void
    {
        $event = EventFactory::new()->published()->create();

        // Create 3 RSVPs with different email scenarios
        RSVPFactory::new()->forEvent($event)->create(['email' => 'rsvp1-'.uniqid().'@example.com']);
        RSVPFactory::new()->forEvent($event)->create(['email' => 'rsvp2-'.uniqid().'@example.com']);
        RSVPFactory::new()->forEvent($event)->create(['email' => '']); // No email

        $recipients = $this->resolver->resolve(EmailPurpose::RSVP, $event);

        $this->assertCount(2, $recipients, 'Should only include RSVPs with non-empty emails');
    }

    public function testResolveTicketRecipientsFiltersByStatus(): void
    {
        $event = EventFactory::new()->published()->ticketed()->create();
        $product = ProductFactory::new()->ticket()->forEvent($event)->create();

        // Create tickets with different statuses using forEvent (not forProduct)
        TicketFactory::new()->forEvent($event)->paid()->withEmail('paid-'.uniqid().'@example.com')->create();
        TicketFactory::new()->forEvent($event)->reserved()->withEmail('reserved-'.uniqid().'@example.com')->create();
        TicketFactory::new()->forEvent($event)->available()->withEmail('pending-'.uniqid().'@example.com')->create();

        $recipients = $this->resolver->resolve(EmailPurpose::TICKET, $event);

        // Should include: paid and reserved (2 recipients)
        $this->assertCount(2, $recipients, 'Should include paid and reserved statuses');
    }

    public function testResolveSelectedArtistFiltersStartTime(): void
    {
        $event = EventFactory::new()->published()->create();

        // Create member with artist
        $member1 = MemberFactory::new()->active()->create(['email' => 'selected-'.uniqid().'@example.com']);
        $artist1 = ArtistFactory::new()->withMember($member1)->create();

        $member2 = MemberFactory::new()->active()->create(['email' => 'notselected-'.uniqid().'@example.com']);
        $artist2 = ArtistFactory::new()->withMember($member2)->create();

        // Artist WITH startTime - should be included
        EventArtistInfoFactory::new()->forEvent($event)->create([
            'Artist' => $artist1,
            'StartTime' => new \DateTimeImmutable('2025-01-01 20:00:00'),
        ]);

        // Artist WITHOUT startTime - should be excluded
        EventArtistInfoFactory::new()->forEvent($event)->create([
            'Artist' => $artist2,
            'StartTime' => null,
        ]);

        $recipients = $this->resolver->resolve(EmailPurpose::SELECTED_ARTIST, $event);

        $this->assertCount(1, $recipients, 'Should only include artists with startTime set');
        $this->assertMatchesRegularExpression('/selected-/', $recipients[0]->email);
    }

    public function testResolveAktiivitQueriesRealMembers(): void
    {
        // Create active members with all required flags
        MemberFactory::new()->active()->create([
            'email' => 'active1-'.uniqid().'@example.com',
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);
        MemberFactory::new()->active()->create([
            'email' => 'active2-'.uniqid().'@example.com',
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        // Inactive member - should be excluded
        MemberFactory::new()->inactive()->create([
            'email' => 'inactive-'.uniqid().'@example.com',
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        $recipients = $this->resolver->resolve(EmailPurpose::AKTIIVIT, null);

        $this->assertGreaterThanOrEqual(2, \count($recipients), 'Should include at least 2 active members');
        $emails = array_map(static fn ($r) => $r->email, $recipients);
        $activeCount = \count(array_filter($emails, static fn ($e) => str_starts_with($e, 'active')));
        $this->assertGreaterThanOrEqual(2, $activeCount);
    }

    public function testResolveMultipleDeduplicatesAcrossPurposes(): void
    {
        $event = EventFactory::new()->published()->ticketed()->create();

        // Create member with artist
        $sharedEmail = 'shared-'.uniqid().'@example.com';
        $member = MemberFactory::new()->active()->create(['email' => $sharedEmail]);
        $artist = ArtistFactory::new()->withMember($member)->create();

        // Create RSVP
        RSVPFactory::new()->forEvent($event)->create(['email' => $sharedEmail]);

        // Create ticket
        TicketFactory::new()->forEvent($event)->paid()->withEmail($sharedEmail)->create();

        // Create artist signup with startTime
        EventArtistInfoFactory::new()->forEvent($event)->create([
            'Artist' => $artist,
            'StartTime' => new \DateTimeImmutable('2025-01-01 20:00:00'),
        ]);

        $recipients = $this->resolver->resolveMultiple([
            EmailPurpose::RSVP,
            EmailPurpose::TICKET,
            EmailPurpose::SELECTED_ARTIST,
        ], $event);

        // Should only appear once despite being in 3 purposes
        $emails = array_map(static fn ($r) => $r->email, $recipients);
        $this->assertCount(1, array_filter($emails, static fn ($e) => $e === $sharedEmail));
    }

    public function testResolveTiedotusIncludesAllVerifiedMembers(): void
    {
        // Tiedotus should include all email-verified members who allow info mails
        MemberFactory::new()->active()->create([
            'email' => 'activeinfo-'.uniqid().'@example.com',
            'emailVerified' => true,
            'allowInfoMails' => true,
        ]);
        MemberFactory::new()->inactive()->create([
            'email' => 'inactiveinfo-'.uniqid().'@example.com',
            'emailVerified' => true,
            'allowInfoMails' => true,
        ]);

        $recipients = $this->resolver->resolve(EmailPurpose::TIEDOTUS, null);

        $this->assertGreaterThanOrEqual(2, \count($recipients), 'Should include both active and inactive members');
    }

    public function testResolveThrowsWhenEventRequiredButMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an Event context');

        $this->resolver->resolve(EmailPurpose::RSVP, null);
    }

    public function testResolveMultipleDeduplicatesSamePurposeCalledTwice(): void
    {
        $event = EventFactory::new()->published()->create();

        // Create 2 RSVPs
        RSVPFactory::new()->forEvent($event)->create(['email' => 'rsvp-dedup-'.uniqid().'@example.com']);
        RSVPFactory::new()->forEvent($event)->create(['email' => 'rsvp-dedup2-'.uniqid().'@example.com']);

        // Call resolveMultiple with RSVP purpose twice - without deduplication this would give 4 recipients
        $recipients = $this->resolver->resolveMultiple([
            EmailPurpose::RSVP,
            EmailPurpose::RSVP,
        ], $event);

        // Should deduplicate to 2 recipients (not 4)
        $this->assertCount(2, $recipients, 'Should deduplicate when same purpose appears multiple times');
    }
}
