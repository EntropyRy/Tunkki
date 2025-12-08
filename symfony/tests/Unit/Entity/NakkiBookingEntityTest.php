<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\NakkiBooking
 */
final class NakkiBookingEntityTest extends TestCase
{
    public function testSetAndGetNakki(): void
    {
        $booking = new NakkiBooking();
        $nakki = $this->createStub(Nakki::class);

        $booking->setNakki($nakki);
        $this->assertSame($nakki, $booking->getNakki());

        // Removed null setter test for Nakki (non-nullable)
    }

    public function testSetAndGetMember(): void
    {
        $booking = new NakkiBooking();
        $member = $this->createStub(Member::class);

        $booking->setMember($member);
        $this->assertSame($member, $booking->getMember());

        $booking->setMember(null);
        $this->assertNull($booking->getMember());
    }

    public function testSetAndGetStartAtEndAt(): void
    {
        $booking = new NakkiBooking();
        $start = new \DateTimeImmutable('2025-01-01 10:00:00');
        $end = new \DateTimeImmutable('2025-01-01 12:00:00');

        $booking->setStartAt($start);
        $booking->setEndAt($end);

        $this->assertSame($start, $booking->getStartAt());
        $this->assertSame($end, $booking->getEndAt());
    }

    public function testSetAndGetEvent(): void
    {
        $booking = new NakkiBooking();
        $event = $this->createStub(Event::class);

        $booking->setEvent($event);
        $this->assertSame($event, $booking->getEvent());

        // Removed null setter test for Event (non-nullable)
    }

    public function testGetMemberEmailWithMember(): void
    {
        $booking = new NakkiBooking();
        $member = $this->createStub(Member::class);
        $member->method('getEmail')->willReturn('member@example.com');

        $booking->setMember($member);
        $this->assertSame('member@example.com', $booking->getMemberEmail());
    }

    public function testGetMemberEmailWithoutMember(): void
    {
        $booking = new NakkiBooking();
        $booking->setMember(null);
        $this->assertNull($booking->getMemberEmail());
    }

    public function testMemberHasEventTicketTrue(): void
    {
        $booking = new NakkiBooking();
        $member = $this->createStub(Member::class);
        $event = $this->createStub(Event::class);

        $event->method('memberHasTicket')->with($member)->willReturn(true);

        $booking->setMember($member);
        $booking->setEvent($event);

        $this->assertTrue($booking->memberHasEventTicket());
    }

    public function testMemberHasEventTicketFalse(): void
    {
        $booking = new NakkiBooking();
        $member = $this->createStub(Member::class);
        $event = $this->createStub(Event::class);

        $event->method('memberHasTicket')->with($member)->willReturn(false);

        $booking->setMember($member);
        $booking->setEvent($event);

        $this->assertFalse($booking->memberHasEventTicket());
    }

    public function testMemberHasEventTicketNoMember(): void
    {
        $booking = new NakkiBooking();
        $event = $this->createStub(Event::class);

        $booking->setMember(null);
        $booking->setEvent($event);

        $this->assertFalse($booking->memberHasEventTicket());
    }

    public function testToStringWithNakkiAndEventRequired(): void
    {
        $booking = new NakkiBooking();
        $nakki = $this->createStub(Nakki::class);
        $event = $this->createStub(Event::class);

        $event->method('isNakkiRequiredForTicketReservation')->willReturn(true);
        $booking->setNakki($nakki);
        $booking->setEvent($event);

        $this->assertStringContainsString((string) $event, (string) $booking);
        $this->assertStringContainsString((string) $nakki, (string) $booking);
    }

    public function testToStringWithNakkiAndEventNotRequired(): void
    {
        $booking = new NakkiBooking();
        $nakki = $this->createStub(Nakki::class);
        $event = $this->createStub(Event::class);

        $event
            ->method('isNakkiRequiredForTicketReservation')
            ->willReturn(false);
        $booking->setNakki($nakki);
        $booking->setEvent($event);

        $booking->setStartAt(new \DateTimeImmutable('2025-01-01 10:00:00'));

        $str = (string) $booking;
        $this->assertStringContainsString((string) $event, $str);
        $this->assertStringContainsString((string) $nakki, $str);
        $this->assertStringContainsString('10:00', $str);
    }

    public function testEdgeCaseSetters(): void
    {
        $booking = new NakkiBooking();
        // Removed null setter tests for Nakki and Event (non-nullable)
        $booking->setMember(null);
        // $booking->setStartAt(null); // Do not pass null to setStartAt, which requires DateTimeImmutable
        // $booking->setEndAt(null); // Do not pass null to setEndAt, which requires DateTimeImmutable

        $this->assertNull($booking->getMember());
        // $this->assertNull($booking->getEndAt()); // Removed: endAt is not initialized and should not be asserted as null
    }
}
