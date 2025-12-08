<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
use App\Service\NakkiDisplayService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\NakkiDisplayService
 */
final class NakkiDisplayServiceTest extends TestCase
{
    private NakkiDisplayService $service;

    protected function setUp(): void
    {
        $this->service = new NakkiDisplayService();
    }

    public function testAddNakkiToArrayCreatesNewEntry(): void
    {
        $booking = $this->createNakkiBooking(
            'Security',
            'Help with security',
            '2025-01-01 10:00',
            '2025-01-01 12:00',
        );

        $result = $this->service->addNakkiToArray([], $booking, 'fi');

        $this->assertArrayHasKey('Security', $result);
        $this->assertSame('Help with security', $result['Security']['description']);
        $this->assertCount(1, $result['Security']['bookings']);
        $this->assertSame($booking, $result['Security']['bookings'][0]);
        $this->assertArrayHasKey('2', $result['Security']['durations']); // 2 hours
        $this->assertSame('2', $result['Security']['durations']['2']);
    }

    public function testAddNakkiToArrayAppendsToExistingEntry(): void
    {
        $booking1 = $this->createNakkiBooking(
            'Security',
            'Help with security',
            '2025-01-01 10:00',
            '2025-01-01 12:00',
        );

        $booking2 = $this->createNakkiBooking(
            'Security',
            'Help with security',
            '2025-01-01 14:00',
            '2025-01-01 17:00',
        );

        $nakkis = $this->service->addNakkiToArray([], $booking1, 'fi');
        $result = $this->service->addNakkiToArray($nakkis, $booking2, 'fi');

        $this->assertArrayHasKey('Security', $result);
        $this->assertCount(2, $result['Security']['bookings']);
        $this->assertSame($booking1, $result['Security']['bookings'][0]);
        $this->assertSame($booking2, $result['Security']['bookings'][1]);
        $this->assertArrayHasKey('2', $result['Security']['durations']); // 2 hours from first
        $this->assertArrayHasKey('3', $result['Security']['durations']); // 3 hours from second
    }

    public function testAddNakkiToArrayCalculatesDurationCorrectly(): void
    {
        $booking30min = $this->createNakkiBooking(
            'Quick Task',
            'Short task',
            '2025-01-01 10:00',
            '2025-01-01 10:30',
        );

        $result = $this->service->addNakkiToArray([], $booking30min, 'en');

        // Duration formatting with '%h' gives 0 for 30 minutes
        $this->assertArrayHasKey('0', $result['Quick Task']['durations']);
    }

    public function testAddNakkiToArrayHandlesMultipleDifferentNakkis(): void
    {
        $bookingSecurity = $this->createNakkiBooking(
            'Security',
            'Security work',
            '2025-01-01 10:00',
            '2025-01-01 12:00',
        );

        $bookingBar = $this->createNakkiBooking(
            'Bar',
            'Bar service',
            '2025-01-01 14:00',
            '2025-01-01 16:00',
        );

        $nakkis = $this->service->addNakkiToArray([], $bookingSecurity, 'fi');
        $result = $this->service->addNakkiToArray($nakkis, $bookingBar, 'fi');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('Security', $result);
        $this->assertArrayHasKey('Bar', $result);
    }

    public function testGetNakkiFromGroupReturnsEmptyArrayForNoNakkis(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getNakkis')->willReturn(new ArrayCollection());

        $member = $this->createStub(Member::class);

        $result = $this->service->getNakkiFromGroup($event, $member, [], 'fi');

        $this->assertSame([], $result);
    }

    public function testGetNakkiFromGroupSkipsDisabledBookings(): void
    {
        $nakki = $this->createStub(Nakki::class);
        $nakki->method('isDisableBookings')->willReturn(true);

        $event = $this->createStub(Event::class);
        $event->method('getNakkis')->willReturn(new ArrayCollection([$nakki]));

        $member = $this->createStub(Member::class);

        $result = $this->service->getNakkiFromGroup($event, $member, [], 'fi');

        $this->assertSame([], $result);
    }

    public function testGetNakkiFromGroupIncludesSelectedBookings(): void
    {
        $definition = $this->createStub(NakkiDefinition::class);
        $definition->method('getName')->with('fi')->willReturn('Security');
        $definition->method('getDescription')->with('fi')->willReturn('Security work');

        $nakki = $this->createStub(Nakki::class);
        $nakki->method('isDisableBookings')->willReturn(false);
        $nakki->method('getDefinition')->willReturn($definition);
        $nakki->method('getNakkiBookings')->willReturn(new ArrayCollection());

        $booking = $this->createStub(NakkiBooking::class);
        $booking->method('getNakki')->willReturn($nakki);
        $booking->method('getStartAt')->willReturn(new \DateTimeImmutable('2025-01-01 10:00'));
        $booking->method('getEndAt')->willReturn(new \DateTimeImmutable('2025-01-01 12:00'));

        $event = $this->createStub(Event::class);
        $event->method('getNakkis')->willReturn(new ArrayCollection([$nakki]));

        $member = $this->createStub(Member::class);

        $result = $this->service->getNakkiFromGroup($event, $member, [$booking], 'fi');

        $this->assertArrayHasKey('Security', $result);
        $this->assertCount(1, $result['Security']['bookings']);
    }

    public function testGetNakkiFromGroupIncludesUnreservedBookings(): void
    {
        $definition = $this->createStub(NakkiDefinition::class);
        $definition->method('getName')->with('fi')->willReturn('Bar');
        $definition->method('getDescription')->with('fi')->willReturn('Bar service');

        $unreservedBooking = $this->createStub(NakkiBooking::class);
        $unreservedBooking->method('getMember')->willReturn(null); // Not reserved
        $unreservedBooking->method('getStartAt')->willReturn(new \DateTimeImmutable('2025-01-01 14:00'));
        $unreservedBooking->method('getEndAt')->willReturn(new \DateTimeImmutable('2025-01-01 16:00'));

        $nakki = $this->createStub(Nakki::class);
        $nakki->method('isDisableBookings')->willReturn(false);
        $nakki->method('getDefinition')->willReturn($definition);
        $nakki->method('getNakkiBookings')->willReturn(new ArrayCollection([$unreservedBooking]));

        // Set up the unreservedBooking to return this nakki
        $unreservedBooking->method('getNakki')->willReturn($nakki);

        $event = $this->createStub(Event::class);
        $event->method('getNakkis')->willReturn(new ArrayCollection([$nakki]));

        $member = $this->createStub(Member::class);

        $result = $this->service->getNakkiFromGroup($event, $member, [], 'fi');

        $this->assertArrayHasKey('Bar', $result);
        $this->assertCount(1, $result['Bar']['bookings']);
    }

    public function testGetNakkiFromGroupSkipsReservedBookingsWhenNotSelected(): void
    {
        $definition = $this->createStub(NakkiDefinition::class);
        $definition->method('getName')->with('fi')->willReturn('Cleanup');
        $definition->method('getDescription')->with('fi')->willReturn('Cleanup work');

        $reservedBooking = $this->createStub(NakkiBooking::class);
        $otherMember = $this->createStub(Member::class);
        $reservedBooking->method('getMember')->willReturn($otherMember); // Already reserved by someone
        $reservedBooking->method('getStartAt')->willReturn(new \DateTimeImmutable('2025-01-01 18:00'));
        $reservedBooking->method('getEndAt')->willReturn(new \DateTimeImmutable('2025-01-01 20:00'));

        $nakki = $this->createStub(Nakki::class);
        $nakki->method('isDisableBookings')->willReturn(false);
        $nakki->method('getDefinition')->willReturn($definition);
        $nakki->method('getNakkiBookings')->willReturn(new ArrayCollection([$reservedBooking]));

        $reservedBooking->method('getNakki')->willReturn($nakki);

        $event = $this->createStub(Event::class);
        $event->method('getNakkis')->willReturn(new ArrayCollection([$nakki]));

        $member = $this->createStub(Member::class);

        $result = $this->service->getNakkiFromGroup($event, $member, [], 'fi');

        // Should not include the reserved booking
        $this->assertSame([], $result);
    }

    public function testGetNakkiFromGroupPrioritizesSelectedOverUnreserved(): void
    {
        $definition = $this->createStub(NakkiDefinition::class);
        $definition->method('getName')->with('en')->willReturn('Setup');
        $definition->method('getDescription')->with('en')->willReturn('Setup work');

        $selectedBooking = $this->createStub(NakkiBooking::class);
        $selectedBooking->method('getStartAt')->willReturn(new \DateTimeImmutable('2025-01-01 08:00'));
        $selectedBooking->method('getEndAt')->willReturn(new \DateTimeImmutable('2025-01-01 10:00'));

        $unreservedBooking = $this->createStub(NakkiBooking::class);
        $unreservedBooking->method('getMember')->willReturn(null);
        $unreservedBooking->method('getStartAt')->willReturn(new \DateTimeImmutable('2025-01-01 10:00'));
        $unreservedBooking->method('getEndAt')->willReturn(new \DateTimeImmutable('2025-01-01 12:00'));

        $nakki = $this->createStub(Nakki::class);
        $nakki->method('isDisableBookings')->willReturn(false);
        $nakki->method('getDefinition')->willReturn($definition);
        $nakki->method('getNakkiBookings')->willReturn(new ArrayCollection([$unreservedBooking]));

        $selectedBooking->method('getNakki')->willReturn($nakki);
        $unreservedBooking->method('getNakki')->willReturn($nakki);

        $event = $this->createStub(Event::class);
        $event->method('getNakkis')->willReturn(new ArrayCollection([$nakki]));

        $member = $this->createStub(Member::class);

        $result = $this->service->getNakkiFromGroup($event, $member, [$selectedBooking], 'en');

        // Should only include the selected booking, not add the unreserved one
        $this->assertArrayHasKey('Setup', $result);
        $this->assertCount(1, $result['Setup']['bookings']);
        $this->assertSame($selectedBooking, $result['Setup']['bookings'][0]);
    }

    /**
     * Helper to create a NakkiBooking with mocked relationships.
     */
    private function createNakkiBooking(
        string $name,
        string $description,
        string $startTime,
        string $endTime,
    ): NakkiBooking {
        $definition = $this->createStub(NakkiDefinition::class);
        $definition->method('getName')->willReturn($name);
        $definition->method('getDescription')->willReturn($description);

        $nakki = $this->createStub(Nakki::class);
        $nakki->method('getDefinition')->willReturn($definition);

        $booking = $this->createStub(NakkiBooking::class);
        $booking->method('getNakki')->willReturn($nakki);
        $booking->method('getStartAt')->willReturn(new \DateTimeImmutable($startTime));
        $booking->method('getEndAt')->willReturn(new \DateTimeImmutable($endTime));

        return $booking;
    }
}
