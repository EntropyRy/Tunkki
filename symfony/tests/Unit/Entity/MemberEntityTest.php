<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Member;
use PHPUnit\Framework\TestCase;

class MemberEntityTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $member = new Member();

        // ID should be null before persistence
        $this->assertNull($member->getId());

        // Default values
        $this->assertSame('', $member->getFirstname());
        $this->assertSame('', $member->getLastname());
        $this->assertNull($member->getEmail());
        $this->assertNull($member->getPhone());
        $this->assertNull($member->getCityOfResidence());
        $this->assertNull($member->getCreatedAt());
        $this->assertNull($member->getUpdatedAt());
        $this->assertFalse($member->getIsActiveMember());
        $this->assertFalse($member->getRejectReasonSent());
        $this->assertFalse($member->getStudentUnionMember());
        $this->assertNull($member->getApplication());
        $this->assertNull($member->getRejectReason());
        $this->assertNull($member->getApplicationDate());
        $this->assertNull($member->getApplicationHandledDate());
        $this->assertNull($member->getAcceptedAsHonoraryMember());
        $this->assertFalse($member->getIsFullMember());
        $this->assertSame('fi', $member->getLocale());
    }

    public function testSettersAndGetters(): void
    {
        $member = new Member();

        // Names
        $member->setFirstname('Matti');
        $member->setLastname('Meikäläinen');
        $this->assertSame('Matti', $member->getFirstname());
        $this->assertSame('Meikäläinen', $member->getLastname());
        $this->assertSame('Matti Meikäläinen', $member->getName());

        // Email
        $member->setEmail('test@example.com');
        $this->assertSame('test@example.com', $member->getEmail());

        // Changing email resets verification
        $member->setEmailVerified(true);
        $member->setEmail('other@example.com');
        $this->assertFalse($member->isEmailVerified());

        // Phone, City
        $member->setPhone('0401234567');
        $member->setCityOfResidence('Helsinki');
        $this->assertSame('0401234567', $member->getPhone());
        $this->assertSame('Helsinki', $member->getCityOfResidence());

        // Booleans
        $member->setIsActiveMember(true);
        $member->setRejectReasonSent(true);
        $member->setStudentUnionMember(true);
        $member->setIsFullMember(true);
        $this->assertTrue($member->getIsActiveMember());
        $this->assertTrue($member->getRejectReasonSent());
        $this->assertTrue($member->getStudentUnionMember());
        $this->assertTrue($member->getIsFullMember());

        // Application, reject reason
        $member->setApplication('Test application');
        $member->setRejectReason('No reason');
        $this->assertSame('Test application', $member->getApplication());
        $this->assertSame('No reason', $member->getRejectReason());

        // Dates
        $now = new \DateTimeImmutable();
        $member->setCreatedAt($now);
        $member->setUpdatedAt($now);
        $this->assertSame($now, $member->getCreatedAt());
        $this->assertSame($now, $member->getUpdatedAt());

        $date = new \DateTime();
        $member->setApplicationDate($date);
        $member->setApplicationHandledDate($date);
        $member->setAcceptedAsHonoraryMember($now);
        $this->assertSame($date, $member->getApplicationDate());
        $this->assertSame($date, $member->getApplicationHandledDate());
        $this->assertSame($now, $member->getAcceptedAsHonoraryMember());

        // Locale
        $member->setLocale('en');
        $this->assertSame('en', $member->getLocale());

        // Code
        $member->setCode('ABC123');
        $this->assertSame('ABC123', $member->getCode());

        // Info mails
        $member->setAllowInfoMails(false);
        $member->setAllowActiveMemberMails(false);
        $this->assertFalse($member->isAllowInfoMails());
        $this->assertFalse($member->isAllowActiveMemberMails());

        // Epics username
        $member->setEpicsUsername('epicsuser');
        $this->assertSame('epicsuser', $member->getEpicsUsername());
    }

    public function testCollectionDefaultsAndAddRemove(): void
    {
        $member = new Member();

        // Artist collection
        $this->assertCount(0, $member->getArtist());
        $mockArtist = $this->createMock(\App\Entity\Artist::class);
        $mockArtist->method('getId')->willReturn(1);
        $member->addArtist($mockArtist);
        $this->assertCount(1, $member->getArtist());
        $this->assertSame($mockArtist, $member->getArtistWithId(1));
        $member->removeArtist($mockArtist);
        $this->assertCount(0, $member->getArtist());

        // DoorLogs
        $this->assertCount(0, $member->getDoorLogs());
        $mockDoorLog = $this->createMock(\App\Entity\DoorLog::class);
        $member->addDoorLog($mockDoorLog);
        $this->assertCount(1, $member->getDoorLogs());
        $member->removeDoorLog($mockDoorLog);
        $this->assertCount(0, $member->getDoorLogs());

        // RSVPs
        $this->assertCount(0, $member->getRSVPs());
        $mockRSVP = $this->createMock(\App\Entity\RSVP::class);
        $member->addRSVP($mockRSVP);
        $this->assertCount(1, $member->getRSVPs());
        $member->removeRSVP($mockRSVP);
        $this->assertCount(0, $member->getRSVPs());

        // NakkiBookings
        $this->assertCount(0, $member->getNakkiBookings());
        $mockNakkiBooking = $this->createMock(\App\Entity\NakkiBooking::class);
        $member->addNakkiBooking($mockNakkiBooking);
        $this->assertCount(1, $member->getNakkiBookings());
        $member->removeNakkiBooking($mockNakkiBooking);
        $this->assertCount(0, $member->getNakkiBookings());

        // Tickets
        $this->assertCount(0, $member->getTickets());
        $mockTicket = $this->createMock(\App\Entity\Ticket::class);
        $member->addTicket($mockTicket);
        $this->assertCount(1, $member->getTickets());
        $member->removeTicket($mockTicket);
        $this->assertCount(0, $member->getTickets());

        // ResponsibleForNakkis
        $this->assertCount(0, $member->getResponsibleForNakkis());
        $mockNakki = $this->createMock(\App\Entity\Nakki::class);
        $member->addResponsibleForNakki($mockNakki);
        $this->assertCount(1, $member->getResponsibleForNakkis());
        $member->removeResponsibleForNakki($mockNakki);
        $this->assertCount(0, $member->getResponsibleForNakkis());

        // HappeningBooking
        $this->assertCount(0, $member->getHappeningBooking());
        $mockHappeningBooking = $this->createMock(
            \App\Entity\HappeningBooking::class,
        );
        $member->addHappeningBooking($mockHappeningBooking);
        $this->assertCount(1, $member->getHappeningBooking());
        $member->removeHappeningBooking($mockHappeningBooking);
        $this->assertCount(0, $member->getHappeningBooking());

        // Happenings
        $this->assertCount(0, $member->getHappenings());
        $mockHappening = $this->createMock(\App\Entity\Happening::class);
        $member->addHappening($mockHappening);
        $this->assertCount(1, $member->getHappenings());
        $member->removeHappening($mockHappening);
        $this->assertCount(0, $member->getHappenings());
    }

    public function testLifecycleHooks(): void
    {
        $member = new Member();
        $this->assertNull($member->getCreatedAt());
        $this->assertNull($member->getUpdatedAt());

        $member->setCreatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $member->getCreatedAt(),
        );
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $member->getUpdatedAt(),
        );

        $oldUpdated = $member->getUpdatedAt();
        sleep(1);
        $member->setUpdatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $member->getUpdatedAt(),
        );
        $this->assertNotEquals($oldUpdated, $member->getUpdatedAt());
    }

    public function testCanVoteLogic(): void
    {
        $member = new Member();

        // Not full member, not student union member
        $this->assertFalse($member->canVote());

        // Full member
        $member->setIsFullMember(true);
        $this->assertTrue($member->canVote());

        // Student union member
        $member->setStudentUnionMember(true);
        $member->setIsFullMember(false);
        $this->assertTrue($member->canVote());
    }
}
