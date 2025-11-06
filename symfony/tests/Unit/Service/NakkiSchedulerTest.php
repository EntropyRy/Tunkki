<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
use App\Service\NakkiScheduler;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * Tests the NakkiScheduler service for booking slot generation and reconciliation.
 *
 * Validates:
 * - Slot generation from nakki time ranges
 * - Booking creation, preservation, removal
 * - Conflict detection (assigned bookings that no longer fit schedule)
 * - Force regeneration (destructive rebuild)
 * - Edge cases (zero interval, inverted times, large iteration counts)
 * - Result object structure and helper methods
 */
final class NakkiSchedulerTest extends FixturesWebTestCase
{
    private NakkiScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = static::getContainer()->get(NakkiScheduler::class);
    }

    /* -----------------------------------------------------------------
     * initialise() - Create slots for new nakki
     * ----------------------------------------------------------------- */
    public function testInitialiseCreatesBookingsForNewNakki(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 14:00:00',
            interval: 'PT1H' // 1 hour
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(4, $result->created, 'Should create 4 one-hour slots');
        self::assertCount(0, $result->removed);
        self::assertCount(0, $result->preserved);
        self::assertCount(0, $result->conflicts);
        self::assertNull($result->warning);
        self::assertFalse($result->hasConflicts());
        self::assertTrue($result->hasChanges());

        // Verify slot times (use format without timezone to avoid timezone issues)
        self::assertSame('2025-01-15 10:00:00', $result->created[0]->getStartAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 11:00:00', $result->created[0]->getEndAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 13:00:00', $result->created[3]->getStartAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 14:00:00', $result->created[3]->getEndAt()->format('Y-m-d H:i:s'));
    }

    public function testInitialiseWith30MinuteIntervals(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT30M' // 30 minutes
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(4, $result->created, 'Should create 4 thirty-minute slots');
        self::assertSame('2025-01-15 10:00:00', $result->created[0]->getStartAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 10:30:00', $result->created[0]->getEndAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 11:30:00', $result->created[3]->getStartAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 12:00:00', $result->created[3]->getEndAt()->format('Y-m-d H:i:s'));
    }

    public function testInitialiseWithPartialFinalSlot(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:30:00', // Not evenly divisible
            interval: 'PT1H'
        );

        $result = $this->scheduler->initialise($nakki);

        // Only complete slots are created
        self::assertCount(2, $result->created, 'Should only create complete slots');
        self::assertSame('2025-01-15 11:00:00', $result->created[1]->getStartAt()->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 12:00:00', $result->created[1]->getEndAt()->format('Y-m-d H:i:s'));
        // Partial slot from 12:00-12:30 is not created
    }

    /* -----------------------------------------------------------------
     * synchronise() - Reconcile existing bookings with schedule changes
     * ----------------------------------------------------------------- */
    public function testSynchronisePreservesMatchingBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        // Create initial bookings
        $booking1 = $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 11:00:00');
        $booking2 = $this->createBooking($nakki, '2025-01-15 11:00:00', '2025-01-15 12:00:00');

        $result = $this->scheduler->synchronise($nakki);

        self::assertCount(0, $result->created);
        self::assertCount(0, $result->removed);
        self::assertCount(2, $result->preserved, 'Both existing bookings should be preserved');
        self::assertCount(0, $result->conflicts);
        self::assertFalse($result->hasChanges());
    }

    public function testSynchroniseAddsNewSlots(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        // Only create first booking
        $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 11:00:00');

        $result = $this->scheduler->synchronise($nakki);

        self::assertCount(1, $result->created, 'Should create missing second slot');
        self::assertCount(0, $result->removed);
        self::assertCount(1, $result->preserved);
        self::assertCount(0, $result->conflicts);
        self::assertTrue($result->hasChanges());

        self::assertSame('2025-01-15 11:00:00', $result->created[0]->getStartAt()->format('Y-m-d H:i:s'));
    }

    public function testSynchroniseRemovesUnassignedOutOfRangeBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        // Create bookings with one outside the new range
        $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 11:00:00');
        $outdated = $this->createBooking($nakki, '2025-01-15 12:00:00', '2025-01-15 13:00:00');

        $result = $this->scheduler->synchronise($nakki);

        self::assertCount(1, $result->created, 'Should create missing 11:00-12:00 slot');
        self::assertCount(1, $result->removed, 'Should remove outdated 12:00-13:00 slot');
        self::assertCount(1, $result->preserved);
        self::assertCount(0, $result->conflicts);
        self::assertTrue($result->hasChanges());

        self::assertSame($outdated, $result->removed[0]);
    }

    public function testSynchroniseDetectsConflictsWithAssignedMembers(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        $member = new Member();
        $member->setFirstname('John');
        $member->setLastname('Volunteer');
        $member->setEmail('john.volunteer@test.com');
        $this->em()->persist($member);

        // Create booking with assigned member that will be outside new range
        $assignedBooking = $this->createBooking($nakki, '2025-01-15 12:00:00', '2025-01-15 13:00:00');
        $assignedBooking->setMember($member);

        $result = $this->scheduler->synchronise($nakki);

        self::assertCount(2, $result->created, 'Should create 10:00-11:00 and 11:00-12:00');
        self::assertCount(0, $result->removed, 'Should not remove assigned booking');
        self::assertCount(0, $result->preserved);
        self::assertCount(1, $result->conflicts, 'Should report assigned booking as conflict');
        self::assertTrue($result->hasConflicts());
        self::assertNotNull($result->warning);
        self::assertStringContainsString('1 booking(s) with assigned members', $result->warning);

        self::assertSame($assignedBooking, $result->conflicts[0]);
    }

    public function testSynchroniseUpdatesEndTimeForPreservedBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        $booking = $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 10:45:00'); // Wrong end time
        $originalEnd = $booking->getEndAt();

        $result = $this->scheduler->synchronise($nakki);

        self::assertCount(1, $result->preserved);
        self::assertSame($booking, $result->preserved[0]);

        // End time should be corrected to match interval
        self::assertNotSame($originalEnd, $booking->getEndAt());
        self::assertSame('2025-01-15 11:00:00', $booking->getEndAt()->format('Y-m-d H:i:s'));
    }

    /* -----------------------------------------------------------------
     * forceRegenerate() - Destructive rebuild
     * ----------------------------------------------------------------- */
    public function testForceRegenerateRemovesAllExistingBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        $booking1 = $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 11:00:00');
        $booking2 = $this->createBooking($nakki, '2025-01-15 11:00:00', '2025-01-15 12:00:00');

        $result = $this->scheduler->forceRegenerate($nakki);

        self::assertCount(2, $result->created);
        self::assertCount(2, $result->removed, 'Should remove all existing bookings');
        self::assertCount(0, $result->preserved);
        self::assertCount(0, $result->conflicts);
        self::assertNull($result->warning);
        self::assertTrue($result->hasChanges());

        self::assertTrue(\in_array($booking1, $result->removed, true));
        self::assertTrue(\in_array($booking2, $result->removed, true));
    }

    public function testForceRegenerateRemovesEvenAssignedBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        $member = new Member();
        $member->setFirstname('Jane');
        $member->setLastname('Volunteer');
        $member->setEmail('jane.volunteer@test.com');
        $this->em()->persist($member);

        $assignedBooking = $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 11:00:00');
        $assignedBooking->setMember($member);

        $result = $this->scheduler->forceRegenerate($nakki);

        self::assertCount(2, $result->created);
        self::assertCount(1, $result->removed, 'Should remove even assigned bookings');
        self::assertCount(0, $result->conflicts, 'Force regenerate does not report conflicts');
        self::assertSame($assignedBooking, $result->removed[0]);
    }

    /* -----------------------------------------------------------------
     * Edge cases
     * ----------------------------------------------------------------- */
    public function testEmptyRangeCreatesNoBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 10:00:00', // Same time
            interval: 'PT1H'
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(0, $result->created);
        self::assertFalse($result->hasChanges());
    }

    public function testInvertedRangeCreatesNoBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 12:00:00',
            end: '2025-01-15 10:00:00', // End before start
            interval: 'PT1H'
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(0, $result->created);
    }

    public function testZeroIntervalCreatesNoBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT0S' // Zero interval
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(0, $result->created);
    }

    public function testNegativeIntervalCreatesNoBookings(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H',
            invert: true // Negative interval
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(0, $result->created);
    }

    public function testVeryShortInterval(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 10:05:00',
            interval: 'PT1M' // 1 minute
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertCount(5, $result->created, 'Should create 5 one-minute slots');
    }

    public function testIterationGuardPreventsInfiniteLoop(): void
    {
        // This tests the internal safety mechanism (max 1000 iterations)
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-20 10:00:00', // 5 days
            interval: 'PT1M' // 1 minute would create 7200 slots, but guard limits to 1000
        );

        $result = $this->scheduler->initialise($nakki);

        self::assertLessThanOrEqual(1000, \count($result->created), 'Iteration guard should prevent excessive slots');
    }

    /* -----------------------------------------------------------------
     * NakkiSchedulerResult value object behavior
     * ----------------------------------------------------------------- */
    public function testResultHasConflictsReturnsTrueWhenConflictsExist(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 11:00:00',
            interval: 'PT1H'
        );
        $booking = $this->createBooking($nakki, '2025-01-15 10:00:00', '2025-01-15 11:00:00');

        $result = $this->scheduler->initialise($nakki);
        self::assertFalse($result->hasConflicts());

        // Test with actual conflict
        $member = new Member();
        $member->setFirstname('Test');
        $member->setLastname('User');
        $member->setEmail('test.user@test.com');
        $this->em()->persist($member);

        $nakki2 = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 11:00:00',
            interval: 'PT1H'
        );
        $conflictBooking = $this->createBooking($nakki2, '2025-01-15 11:00:00', '2025-01-15 12:00:00');
        $conflictBooking->setMember($member);

        $result2 = $this->scheduler->synchronise($nakki2);
        self::assertTrue($result2->hasConflicts());
    }

    public function testResultHasChangesReturnsTrueWhenCreatedOrRemoved(): void
    {
        $nakki = $this->createNakki(
            start: '2025-01-15 10:00:00',
            end: '2025-01-15 12:00:00',
            interval: 'PT1H'
        );

        // Has changes when creating
        $result = $this->scheduler->initialise($nakki);
        self::assertTrue($result->hasChanges());

        // Flush to persist the bookings created by initialise
        $this->em()->flush();

        // No changes when nothing created or removed (synchronise with existing bookings)
        $result2 = $this->scheduler->synchronise($nakki);
        self::assertFalse($result2->hasChanges());

        // Has changes when removing
        $nakki->setEndAt(new \DateTimeImmutable('2025-01-15 11:00:00'));
        $result3 = $this->scheduler->synchronise($nakki);
        self::assertTrue($result3->hasChanges());
    }

    /* -----------------------------------------------------------------
     * Helper methods
     * ----------------------------------------------------------------- */
    private function createNakki(
        string $start,
        string $end,
        string $interval,
        bool $invert = false,
    ): Nakki {
        $event = new Event();
        $event->setName('Test Event');
        $event->setUrl('test-event-'.uniqid('', true));
        $event->setEventDate(new \DateTimeImmutable('2025-01-15 10:00:00'));
        $event->setPublishDate(new \DateTimeImmutable('2025-01-01'));
        $event->setPublished(true);
        $this->em()->persist($event);

        $definition = new NakkiDefinition();
        $definition->setNameFi('Test Definition');
        $definition->setNameEn('Test Definition EN');
        $definition->setDescriptionFi('Test');
        $definition->setDescriptionEn('Test');
        $this->em()->persist($definition);

        $nakki = new Nakki();
        $nakki->setEvent($event);
        $nakki->setDefinition($definition);
        $nakki->setStartAt(new \DateTimeImmutable($start));
        $nakki->setEndAt(new \DateTimeImmutable($end));

        $intervalObj = new \DateInterval($interval);
        if ($invert) {
            $intervalObj->invert = 1;
        }
        $nakki->setNakkiInterval($intervalObj);

        $this->em()->persist($nakki);
        $this->em()->flush();

        return $nakki;
    }

    private function createBooking(Nakki $nakki, string $start, string $end): NakkiBooking
    {
        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setEvent($nakki->getEvent());
        $booking->setStartAt(new \DateTimeImmutable($start));
        $booking->setEndAt(new \DateTimeImmutable($end));

        $this->em()->persist($booking);
        $nakki->addNakkiBooking($booking);
        $this->em()->flush();

        return $booking;
    }
}
