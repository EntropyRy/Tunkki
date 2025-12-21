<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Email;

use App\DTO\EmailRecipient;
use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use App\Entity\Member;
use App\Entity\NakkiBooking;
use App\Entity\Nakkikone;
use App\Entity\RSVP;
use App\Entity\Ticket;
use App\Enum\EmailPurpose;
use App\Repository\ArtistRepository;
use App\Repository\MemberRepository;
use App\Service\Email\RecipientResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Email\RecipientResolver
 */
final class RecipientResolverTest extends TestCase
{
    private RecipientResolver $resolver;
    private MemberRepository $memberRepo;
    private ArtistRepository $artistRepo;

    protected function setUp(): void
    {
        $this->memberRepo = $this->createStub(MemberRepository::class);
        $this->artistRepo = $this->createStub(ArtistRepository::class);

        $this->resolver = new RecipientResolver(
            $this->memberRepo,
            $this->artistRepo
        );
    }

    public function testResolveRsvpRecipientsExtractsEmailsFromEvent(): void
    {
        $rsvp1 = $this->createStub(RSVP::class);
        $rsvp1->method('getAvailableEmail')->willReturn('rsvp1@example.com');

        $rsvp2 = $this->createStub(RSVP::class);
        $rsvp2->method('getAvailableEmail')->willReturn('rsvp2@example.com');

        $rsvp3 = $this->createStub(RSVP::class);
        $rsvp3->method('getAvailableEmail')->willReturn(''); // Empty string (no email)

        $event = $this->createStub(Event::class);
        $event->method('getRSVPs')->willReturn(new ArrayCollection([$rsvp1, $rsvp2, $rsvp3]));

        $recipients = $this->resolver->resolve(EmailPurpose::RSVP, $event);

        $this->assertCount(2, $recipients);
        $this->assertContainsOnlyInstancesOf(EmailRecipient::class, $recipients);
        $this->assertSame('rsvp1@example.com', $recipients[0]->email);
        $this->assertSame('rsvp2@example.com', $recipients[1]->email);
    }

    public function testResolveTicketRecipientsFiltersPaidAndReserved(): void
    {
        $ticket1 = $this->createStub(Ticket::class);
        $ticket1->method('getStatus')->willReturn('paid');
        $ticket1->method('getOwnerEmail')->willReturn('paid@example.com');
        $ticket1->method('getEmail')->willReturn(null);

        $ticket2 = $this->createStub(Ticket::class);
        $ticket2->method('getStatus')->willReturn('reserved');
        $ticket2->method('getOwnerEmail')->willReturn(null);
        $ticket2->method('getEmail')->willReturn('reserved@example.com');

        $ticket3 = $this->createStub(Ticket::class);
        $ticket3->method('getStatus')->willReturn('pending');
        $ticket3->method('getOwnerEmail')->willReturn('pending@example.com');

        $ticket4 = $this->createStub(Ticket::class);
        $ticket4->method('getStatus')->willReturn('paid_refund'); // Also starts with 'paid'
        $ticket4->method('getOwnerEmail')->willReturn('refund@example.com');

        $event = $this->createStub(Event::class);
        $event->method('getTickets')->willReturn(new ArrayCollection([
            $ticket1, $ticket2, $ticket3, $ticket4,
        ]));

        $recipients = $this->resolver->resolve(EmailPurpose::TICKET, $event);

        // Should include: paid, reserved, paid_refund (starts with 'paid')
        // Should exclude: pending
        $this->assertCount(3, $recipients);
        $this->assertSame('paid@example.com', $recipients[0]->email);
        $this->assertSame('reserved@example.com', $recipients[1]->email);
        $this->assertSame('refund@example.com', $recipients[2]->email);
    }

    public function testResolveSelectedArtistFiltersStartTime(): void
    {
        // Artist with startTime (selected/scheduled)
        $member1 = $this->createStub(Member::class);
        $member1->method('getEmail')->willReturn('selected@example.com');
        $member1->method('getLocale')->willReturn('en');
        $member1->method('getId')->willReturn(1);

        $artist1 = $this->createStub(Artist::class);
        $artist1->method('getMember')->willReturn($member1);

        $signup1 = $this->createStub(EventArtistInfo::class);
        $signup1->method('getStartTime')->willReturn(new \DateTimeImmutable());
        $signup1->method('getArtist')->willReturn($artist1);

        // Artist without startTime (not selected)
        $member2 = $this->createStub(Member::class);
        $member2->method('getEmail')->willReturn('notselected@example.com');

        $artist2 = $this->createStub(Artist::class);
        $artist2->method('getMember')->willReturn($member2);

        $signup2 = $this->createStub(EventArtistInfo::class);
        $signup2->method('getStartTime')->willReturn(null);
        $signup2->method('getArtist')->willReturn($artist2);

        $event = $this->createStub(Event::class);
        $event->method('getEventArtistInfos')->willReturn(new ArrayCollection([
            $signup1, $signup2,
        ]));

        $recipients = $this->resolver->resolve(EmailPurpose::SELECTED_ARTIST, $event);

        $this->assertCount(1, $recipients);
        $this->assertSame('selected@example.com', $recipients[0]->email);
        $this->assertSame('en', $recipients[0]->locale);
        $this->assertSame(1, $recipients[0]->memberId);
    }

    public function testResolveMultipleDeduplicatesByEmail(): void
    {
        // Same person has both RSVP and ticket
        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getAvailableEmail')->willReturn('duplicate@example.com');

        $ticket = $this->createStub(Ticket::class);
        $ticket->method('getStatus')->willReturn('paid');
        $ticket->method('getOwnerEmail')->willReturn('DUPLICATE@EXAMPLE.COM'); // Different case
        $ticket->method('getEmail')->willReturn(null);

        $event = $this->createStub(Event::class);
        $event->method('getRSVPs')->willReturn(new ArrayCollection([$rsvp]));
        $event->method('getTickets')->willReturn(new ArrayCollection([$ticket]));

        $recipients = $this->resolver->resolveMultiple(
            [EmailPurpose::RSVP, EmailPurpose::TICKET],
            $event
        );

        // Should deduplicate (case-insensitive)
        $this->assertCount(1, $recipients);
        $this->assertSame('duplicate@example.com', $recipients[0]->email);
    }

    public function testResolveThrowsWhenEventRequiredButMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Purpose "rsvp" requires an Event context');

        $this->resolver->resolve(EmailPurpose::RSVP, null);
    }

    public function testResolveNonEventPurposeDoesNotRequireEvent(): void
    {
        // Aktiivit doesn't require an event
        $this->memberRepo->method('findBy')->willReturn([]);

        $recipients = $this->resolver->resolve(EmailPurpose::AKTIIVIT, null);

        $this->assertIsArray($recipients);
    }

    public function testResolveNakkiRecipientsExtractsMembersFromBookings(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getEmail')->willReturn('nakki@example.com');
        $member->method('getLocale')->willReturn('fi');
        $member->method('getId')->willReturn(42);

        $booking = $this->createStub(NakkiBooking::class);
        $booking->method('getMember')->willReturn($member);

        $nakkikone = $this->createStub(Nakkikone::class);
        $nakkikone->method('getBookings')->willReturn(new ArrayCollection([$booking]));

        $event = $this->createStub(Event::class);
        $event->method('getNakkikone')->willReturn($nakkikone);

        $recipients = $this->resolver->resolve(EmailPurpose::NAKKIKONE, $event);

        $this->assertCount(1, $recipients);
        $this->assertSame('nakki@example.com', $recipients[0]->email);
        $this->assertSame('fi', $recipients[0]->locale);
        $this->assertSame(42, $recipients[0]->memberId);
    }
}
