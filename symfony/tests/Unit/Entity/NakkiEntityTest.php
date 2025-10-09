<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Nakki
 */
final class NakkiEntityTest extends TestCase
{
    public function testConstructorInitializesCollectionsAndInterval(): void
    {
        $nakki = new Nakki();
        $this->assertInstanceOf(
            ArrayCollection::class,
            $nakki->getNakkiBookings(),
        );
        $this->assertInstanceOf(
            \DateInterval::class,
            $nakki->getNakkiInterval(),
        );
        $this->assertSame('PT1H', $nakki->getNakkiInterval()->format('PT%hH'));
    }

    public function testSetAndGetDefinition(): void
    {
        $nakki = new Nakki();
        $definition = $this->createMock(NakkiDefinition::class);

        $nakki->setDefinition($definition);
        $this->assertSame($definition, $nakki->getDefinition());

        $nakki->setDefinition(null);
        $this->assertNull($nakki->getDefinition());
    }

    public function testSetAndGetStartAtEndAt(): void
    {
        $nakki = new Nakki();
        $start = new \DateTimeImmutable('2025-01-01 10:00:00');
        $end = new \DateTimeImmutable('2025-01-01 12:00:00');

        $nakki->setStartAt($start);
        $nakki->setEndAt($end);

        $this->assertSame($start, $nakki->getStartAt());
        $this->assertSame($end, $nakki->getEndAt());
    }

    public function testSetAndGetEvent(): void
    {
        $nakki = new Nakki();
        $event = $this->createMock(Event::class);

        $nakki->setEvent($event);
        $this->assertSame($event, $nakki->getEvent());

        $nakki->setEvent(null);
        $this->assertNull($nakki->getEvent());
    }

    public function testSetAndGetNakkiInterval(): void
    {
        $nakki = new Nakki();
        $interval = new \DateInterval('PT2H');
        $nakki->setNakkiInterval($interval);

        $this->assertSame($interval, $nakki->getNakkiInterval());
    }

    public function testAddAndRemoveNakkiBooking(): void
    {
        $nakki = new Nakki();
        $booking = $this->createMock(NakkiBooking::class);

        $nakki->addNakkiBooking($booking);
        $this->assertTrue($nakki->getNakkiBookings()->contains($booking));

        $nakki->removeNakkiBooking($booking);
        $this->assertFalse($nakki->getNakkiBookings()->contains($booking));
    }

    public function testSetAndGetResponsible(): void
    {
        $nakki = new Nakki();
        $member = $this->createMock(Member::class);

        $nakki->setResponsible($member);
        $this->assertSame($member, $nakki->getResponsible());

        $nakki->setResponsible(null);
        $this->assertNull($nakki->getResponsible());
    }

    public function testSetAndGetMattermostChannel(): void
    {
        $nakki = new Nakki();
        $nakki->setMattermostChannel('test-channel');
        $this->assertSame('test-channel', $nakki->getMattermostChannel());

        $nakki->setMattermostChannel(null);
        $this->assertNull($nakki->getMattermostChannel());
    }

    public function testSetAndGetDisableBookings(): void
    {
        $nakki = new Nakki();
        $nakki->setDisableBookings(true);
        $this->assertTrue($nakki->isDisableBookings());

        $nakki->setDisableBookings(false);
        $this->assertFalse($nakki->isDisableBookings());

        $nakki->setDisableBookings(null);
        $this->assertNull($nakki->isDisableBookings());
    }

    public function testToStringReturnsDefinitionNameOrNA(): void
    {
        $nakki = new Nakki();
        $definition = new NakkiDefinition();
        $definition->setNameEn('TestNakki');

        $nakki->setDefinition($definition);
        $this->assertSame('TestNakki', (string) $nakki);

        $nakki->setDefinition(null);
        $this->assertSame('N/A', (string) $nakki);
    }

    public function testGetTimesReturnsCorrectIntervals(): void
    {
        $nakki = new Nakki();
        $nakki->setStartAt(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $nakki->setEndAt(new \DateTimeImmutable('2025-01-01 13:00:00'));
        $nakki->setNakkiInterval(new \DateInterval('PT1H'));

        $times = $nakki->getTimes();
        $this->assertIsArray($times);
        $this->assertCount(3, $times);
        $this->assertEquals(
            '2025-01-01 10:00:00',
            $times[0]->format('Y-m-d H:i:s'),
        );
        $this->assertEquals(
            '2025-01-01 11:00:00',
            $times[1]->format('Y-m-d H:i:s'),
        );
        $this->assertEquals(
            '2025-01-01 12:00:00',
            $times[2]->format('Y-m-d H:i:s'),
        );
    }

    public function testGetMemberByTimeReturnsCorrectMember(): void
    {
        $nakki = new Nakki();
        $member = $this->createMock(Member::class);
        $booking = $this->createMock(NakkiBooking::class);

        $date = new \DateTimeImmutable('2025-01-01 10:00:00');
        $booking->method('getStartAt')->willReturn($date);
        $booking->method('getMember')->willReturn($member);

        $nakki->addNakkiBooking($booking);

        $this->assertSame($member, $nakki->getMemberByTime($date));
    }

    public function testGetMemberByTimeReturnsNullIfNoMatch(): void
    {
        $nakki = new Nakki();
        $date = new \DateTimeImmutable('2025-01-01 10:00:00');
        $this->assertNull($nakki->getMemberByTime($date));
    }

    public function testEdgeCaseSetters(): void
    {
        $nakki = new Nakki();
        $nakki->setDefinition(null);
        // Do not setStartAt(null); setter requires DateTimeImmutable
        // Do not setEndAt(null); setter requires DateTimeImmutable
        $nakki->setEvent(null);
        $nakki->setNakkiInterval(new \DateInterval('PT1H'));
        $nakki->setResponsible(null);
        $nakki->setMattermostChannel(null);
        $nakki->setDisableBookings(null);

        $this->assertNull($nakki->getDefinition());
        // $this->assertNull($nakki->getStartAt()); // skip, since not set to null
        // $this->assertNull($nakki->getEndAt()); // skip, since not set to null
        $this->assertNull($nakki->getEvent());
        $this->assertNull($nakki->getResponsible());
        $this->assertNull($nakki->getMattermostChannel());
        $this->assertNull($nakki->isDisableBookings());
    }
}
