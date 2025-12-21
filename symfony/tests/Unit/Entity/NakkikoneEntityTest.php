<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
use App\Entity\Nakkikone;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Nakkikone
 */
final class NakkikoneEntityTest extends TestCase
{
    public function testDefaultsAndShouldShowLinkInEvent(): void
    {
        $event = new Event();
        $nakkikone = new Nakkikone($event);

        self::assertNull($nakkikone->getId());
        self::assertFalse($nakkikone->shouldShowLinkInEvent());

        $nakkikone->setShowLinkInEvent(true);
        self::assertTrue($nakkikone->shouldShowLinkInEvent());
    }

    public function testAddAndRemoveNakki(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $nakki = $this->createNakki($nakkikone, 'Stage A');

        $nakkikone->addNakki($nakki);
        self::assertCount(1, $nakkikone->getNakkis());
        self::assertSame($nakkikone, $nakki->getNakkikone());

        $nakkikone->removeNakki($nakki);
        self::assertCount(0, $nakkikone->getNakkis());
    }

    public function testAddAndRemoveBooking(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $nakki = $this->createNakki($nakkikone, 'Stage A');
        $booking = $this->createBooking($nakkikone, $nakki);

        $nakkikone->addBooking($booking);
        self::assertCount(1, $nakkikone->getBookings());

        $nakkikone->removeBooking($booking);
        self::assertCount(0, $nakkikone->getBookings());
    }

    public function testRemoveResponsibleAdmin(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $admin = new Member();

        $nakkikone->addResponsibleAdmin($admin);
        self::assertCount(1, $nakkikone->getResponsibleAdmins());

        $nakkikone->removeResponsibleAdmin($admin);
        self::assertCount(0, $nakkikone->getResponsibleAdmins());
    }

    public function testGetResponsibleMemberNakkisRespectsRoles(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $member = new Member();
        $member->setLocale('en');
        $admin = new Member();
        $admin->setLocale('en');

        $nakkiA = $this->createNakki($nakkikone, 'Stage A');
        $nakkiA->setResponsible($member);
        $nakkiB = $this->createNakki($nakkikone, 'Stage B');

        $nakkikone->addNakki($nakkiA);
        $nakkikone->addNakki($nakkiB);
        $nakkikone->addResponsibleAdmin($admin);

        $memberView = $nakkikone->getResponsibleMemberNakkis($member);
        self::assertArrayHasKey('Stage A', $memberView);
        self::assertArrayNotHasKey('Stage B', $memberView);

        $adminView = $nakkikone->getResponsibleMemberNakkis($admin);
        self::assertArrayHasKey('Stage A', $adminView);
        self::assertArrayHasKey('Stage B', $adminView);
    }

    public function testGetMemberNakkisReturnsMemberBooking(): void
    {
        $event = new Event();
        $nakkikone = new Nakkikone($event);
        $member = new Member();
        $member->setLocale('en');

        $nakki = $this->createNakki($nakkikone, 'Stage A');
        $booking = $this->createBooking($nakkikone, $nakki);
        $booking->setMember($member);
        $member->addNakkiBooking($booking);

        $result = $nakkikone->getMemberNakkis($member);
        self::assertArrayHasKey('Stage A', $result);
    }

    public function testGetAllResponsiblesReturnsMap(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $member = new Member();

        $nakkiA = $this->createNakki($nakkikone, 'Stage A');
        $nakkiA->setMattermostChannel('#a');
        $nakkiA->setResponsible($member);
        $nakkiB = $this->createNakki($nakkikone, 'Stage B');
        $nakkiB->setMattermostChannel('#b');

        $nakkikone->addNakki($nakkiA);
        $nakkikone->addNakki($nakkiB);

        $result = $nakkikone->getAllResponsibles('en');

        self::assertArrayHasKey('Stage A', $result);
        self::assertArrayHasKey('Stage B', $result);
        self::assertSame('#a', $result['Stage A']['mattermost']);
        self::assertSame($member, $result['Stage A']['responsible']);
    }

    public function testTicketHolderHasBookingHonorsRequirement(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $member = new Member();

        self::assertNull($nakkikone->ticketHolderHasBooking($member));

        $nakkikone->setRequiredForTicketReservation(true);
        $nakki = $this->createNakki($nakkikone, 'Stage A');
        $booking = $this->createBooking($nakkikone, $nakki);
        $booking->setMember($member);
        $nakkikone->addBooking($booking);

        self::assertSame($booking, $nakkikone->ticketHolderHasBooking($member));
    }

    public function testTicketHolderHasNakkiReturnsNullWhenNotRequired(): void
    {
        $nakkikone = new Nakkikone(new Event());
        $member = new Member();

        self::assertNull($nakkikone->ticketHolderHasNakki($member));
    }

    private function createNakki(
        Nakkikone $nakkikone,
        string $nameEn,
    ): Nakki {
        $definition = new NakkiDefinition();
        $definition->setNameFi($nameEn.' FI');
        $definition->setNameEn($nameEn);

        $nakki = new Nakki();
        $nakki->setNakkikone($nakkikone);
        $nakki->setDefinition($definition);
        $nakki->setStartAt(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $nakki->setEndAt(new \DateTimeImmutable('2025-01-01 12:00:00'));

        return $nakki;
    }

    private function createBooking(
        Nakkikone $nakkikone,
        Nakki $nakki,
    ): NakkiBooking {
        $booking = new NakkiBooking();
        $booking->setNakkikone($nakkikone);
        $booking->setNakki($nakki);
        $booking->setStartAt(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $booking->setEndAt(new \DateTimeImmutable('2025-01-01 11:00:00'));

        return $booking;
    }
}
