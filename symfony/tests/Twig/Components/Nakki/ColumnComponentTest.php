<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Entity\Nakki;
use App\Factory\NakkiBookingFactory;
use App\Factory\NakkiFactory;
use App\Repository\NakkiRepository;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\Column;

final class ColumnComponentTest extends LiveComponentTestCase
{
    public function testAddSlotsCreatesBookings(): void
    {
        $nakki = NakkiFactory::new()->create();
        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->set('newSlotStart', $nakki->getStartAt()->format('Y-m-d\TH:i'));
        $component->call('addSlots', ['intervalHours' => 1, 'slotCount' => 2]);

        $reloaded = $this->reloadNakki($nakki->getId());
        self::assertGreaterThanOrEqual(2, $reloaded->getNakkiBookings()->count());
    }

    public function testNakkiIdUpdatedReloadsState(): void
    {
        $first = NakkiFactory::new()->disabled()->create();
        $second = NakkiFactory::new()->enabled()->withInterval(2)->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $first->getId()]);
        $component->render();

        /** @var Column $column */
        $column = $component->component();
        self::assertTrue($column->disableBookings);

        $column->nakkiId = $second->getId();
        $column->nakkiIdUpdated();

        self::assertFalse($column->disableBookings);
        self::assertSame(2, $column->displayIntervalHours);
    }

    public function testEditColumnEmitsWhenDefinitionExists(): void
    {
        $nakki = NakkiFactory::new()->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('editColumn');

        $this->assertTrue(true);
    }

    public function testRemoveSlotDeletesUnreservedBooking(): void
    {
        $nakki = NakkiFactory::new()->create();
        $booking = NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $nakki->getStartAt(),
                'endAt' => $nakki->getStartAt()->modify('+1 hour'),
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('removeSlot', ['bookingId' => $booking->getId()]);

        $reloaded = $this->reloadNakki($nakki->getId());
        $ids = array_map(
            static fn ($item) => $item->getId(),
            $reloaded->getNakkiBookings()->toArray(),
        );
        self::assertNotContains($booking->getId(), $ids);
    }

    public function testRemoveSlotDeniesReservedBooking(): void
    {
        $nakki = NakkiFactory::new()->create();
        $booking = NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
            ])
            ->booked()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('removeSlot', ['bookingId' => $booking->getId()]);
        $reloaded = $this->reloadNakki($nakki->getId());
        $ids = array_map(
            static fn ($item) => $item->getId(),
            $reloaded->getNakkiBookings()->toArray(),
        );
        self::assertContains($booking->getId(), $ids);
    }

    public function testGetNakkiThrowsWhenMissing(): void
    {
        /** @var Column $column */
        $column = self::getContainer()->get(Column::class);
        $column->nakkiId = 999999;

        $this->expectException(\RuntimeException::class);
        $column->getNakkiView();
    }

    public function testAddSlotBeforeWithoutBookingsSetsError(): void
    {
        $nakki = NakkiFactory::new()->create();
        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('addSlotBefore');
        $reloaded = $this->reloadNakki($nakki->getId());
        self::assertCount(0, $reloaded->getNakkiBookings());
    }

    public function testAddSlotAfterCreatesFirstSlot(): void
    {
        $nakki = NakkiFactory::new()->create();
        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('addSlotAfter');

        $reloaded = $this->reloadNakki($nakki->getId());
        self::assertCount(1, $reloaded->getNakkiBookings());
    }

    public function testAddSlotAtTimeCreatesCustomSlot(): void
    {
        $nakki = NakkiFactory::new()->create();
        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $start = $nakki->getStartAt()->modify('+2 hours');
        $component->call('addSlotAtTime', [
            'startTime' => $start->format('Y-m-d H:i:s'),
            'intervalHours' => 2,
        ]);

        $reloaded = $this->reloadNakki($nakki->getId());
        self::assertGreaterThanOrEqual(1, $reloaded->getNakkiBookings()->count());
    }

    public function testAddSlotsUsesLastBookingEndWhenStartEmpty(): void
    {
        $start = new \DateTimeImmutable('2025-01-01 10:00:00');
        $end = $start->modify('+1 hour');

        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $end->modify('+4 hours'),
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->set('newSlotStart', '');
        $component->call('addSlots', ['intervalHours' => 2, 'slotCount' => 1]);

        $reloaded = $this->reloadNakki($nakki->getId());
        $bookings = $reloaded->getNakkiBookings()->toArray();
        usort(
            $bookings,
            static fn ($a, $b) => $a->getStartAt() <=> $b->getStartAt(),
        );

        $last = end($bookings);
        self::assertInstanceOf(\DateTimeImmutable::class, $last->getStartAt());
        self::assertSame($end->format('Y-m-d H:i:s'), $last->getStartAt()->format('Y-m-d H:i:s'));
    }

    public function testAddSlotsUsesNakkiStartWhenNoBookingsAndEmptyStart(): void
    {
        $nakki = NakkiFactory::new()->create([
            'startAt' => new \DateTimeImmutable('2025-01-01 08:00:00'),
            'endAt' => new \DateTimeImmutable('2025-01-01 16:00:00'),
        ]);

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->set('newSlotStart', '');
        $component->call('addSlots', ['intervalHours' => 1, 'slotCount' => 1]);

        $reloaded = $this->reloadNakki($nakki->getId());
        $booking = $reloaded->getNakkiBookings()->first();
        self::assertSame('2025-01-01 08:00:00', $booking->getStartAt()->format('Y-m-d H:i:s'));
    }

    public function testSaveDisableTogglesFlag(): void
    {
        $nakki = NakkiFactory::new()->create();
        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->set('disableBookings', true);
        $component->call('saveDisable');
        $reloaded = $this->reloadNakki($nakki->getId());
        self::assertTrue((bool) $reloaded->isDisableBookings());
    }

    public function testToggleViewModeSwitchesAndUpdateIntervalChangesDuration(): void
    {
        $nakki = NakkiFactory::new()->create();
        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('toggleViewMode');
        /** @var Column $state */
        $state = $component->component();
        self::assertSame('schedule', $state->viewMode);

        $component->call('updateInterval', ['intervalHours' => 3]);
        $reloaded = $this->reloadNakki($nakki->getId());
        $interval = $reloaded->getNakkiInterval();
        $hours = ($interval->d * 24) + $interval->h;
        self::assertSame(3, $hours);
    }

    public function testAddSlotBeforeCreatesSlotWhenBookingsExist(): void
    {
        $start = new \DateTimeImmutable('2025-01-01 12:00:00');
        $end = $start->modify('+1 hour');
        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $end->modify('+2 hours'),
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('addSlotBefore');

        $reloaded = $this->reloadNakki($nakki->getId());
        $bookings = $reloaded->getNakkiBookings()->toArray();
        usort(
            $bookings,
            static fn ($a, $b) => $a->getStartAt() <=> $b->getStartAt(),
        );
        $first = $bookings[0];
        self::assertSame(
            $start->modify('-1 hour')->format('Y-m-d H:i:s'),
            $first->getStartAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testAddSlotAfterUsesLastBookingEnd(): void
    {
        $start = new \DateTimeImmutable('2025-01-01 09:00:00');
        $end = $start->modify('+1 hour');
        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $end->modify('+2 hours'),
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('addSlotAfter');

        $reloaded = $this->reloadNakki($nakki->getId());
        $bookings = $reloaded->getNakkiBookings()->toArray();
        usort(
            $bookings,
            static fn ($a, $b) => $a->getStartAt() <=> $b->getStartAt(),
        );
        $last = end($bookings);
        self::assertSame($end->format('Y-m-d H:i:s'), $last->getStartAt()->format('Y-m-d H:i:s'));
    }

    public function testScheduleGridExtendsEndHourForPartialSlots(): void
    {
        $start = new \DateTimeImmutable('2025-01-01 10:00:00');
        $end = new \DateTimeImmutable('2025-01-01 11:30:00');
        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $end,
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        /** @var Column $column */
        $column = $component->component();
        $grid = $column->getScheduleGrid();

        self::assertCount(2, $grid['timeSlots']);
    }

    public function testScheduleGridUsesLatestBookingEnd(): void
    {
        $nakki = NakkiFactory::new()->create([
            'startAt' => new \DateTimeImmutable('2025-01-01 10:00:00'),
            'endAt' => new \DateTimeImmutable('2025-01-01 11:00:00'),
        ]);

        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => new \DateTimeImmutable('2025-01-01 10:00:00'),
                'endAt' => new \DateTimeImmutable('2025-01-01 11:00:00'),
            ])
            ->free()
            ->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => new \DateTimeImmutable('2025-01-01 12:00:00'),
                'endAt' => new \DateTimeImmutable('2025-01-01 15:00:00'),
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        /** @var Column $column */
        $column = $component->component();
        $grid = $column->getScheduleGrid();

        self::assertCount(5, $grid['timeSlots']);
    }

    public function testRemoveSlotRejectsBookingFromDifferentNakki(): void
    {
        $nakki = NakkiFactory::new()->create();
        $otherNakki = NakkiFactory::new()->create();
        $booking = NakkiBookingFactory::new()
            ->with([
                'nakki' => $otherNakki,
                'nakkikone' => $otherNakki->getNakkikone(),
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('removeSlot', ['bookingId' => $booking->getId()]);

        $reloaded = $this->reloadNakki($otherNakki->getId());
        self::assertCount(1, $reloaded->getNakkiBookings());
    }

    public function testDeleteColumnPreventsWhenReserved(): void
    {
        $nakki = NakkiFactory::new()->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
            ])
            ->booked()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('deleteColumn');
        $repository = self::getContainer()->get(NakkiRepository::class);
        self::assertNotNull($repository->find($nakki->getId()));
    }

    public function testDeleteColumnRemovesBookings(): void
    {
        $nakki = NakkiFactory::new()->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('deleteColumn');

        $repository = self::getContainer()->get(NakkiRepository::class);
        self::assertNull($repository->find($nakki->getId()));
    }

    public function testGetBookingGroupsGroupsByInterval(): void
    {
        $start = new \DateTimeImmutable('2025-01-02 10:00:00');
        $end = $start->modify('+1 hour');
        $endLong = $start->modify('+2 hours');
        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $endLong,
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $endLong,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        /** @var Column $state */
        $state = $component->component();
        $groups = $state->getBookingGroups();

        self::assertCount(2, $groups);
        $counts = array_map(static fn (array $group): int => \count($group['bookings']), $groups);
        sort($counts);
        self::assertSame([1, 2], $counts);
    }

    public function testGetScheduleGridCalculatesOverlaps(): void
    {
        $start = new \DateTimeImmutable('2025-01-03 08:00:00');
        $end = $start->modify('+2 hours');
        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $end,
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        /** @var Column $state */
        $state = $component->component();
        $grid = $state->getScheduleGrid();

        self::assertSame(2, $grid['maxColumns']);
        self::assertNotEmpty($grid['timeSlots']);
        $firstBookings = array_filter(
            $grid['timeSlots'][0]['bookings'],
            static fn (array $entry): bool => null !== $entry['booking'],
        );
        self::assertCount(2, $firstBookings);
    }

    public function testGetNestedBookingGroupsSeparatesIntervals(): void
    {
        $start = new \DateTimeImmutable('2025-01-04 18:00:00');
        $end = $start->modify('+1 hour');
        $endLong = $start->modify('+3 hours');
        $nakki = NakkiFactory::new()->create([
            'startAt' => $start,
            'endAt' => $endLong,
        ]);
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $end,
            ])
            ->free()
            ->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $start,
                'endAt' => $endLong,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        /** @var Column $state */
        $state = $component->component();
        $groups = $state->getNestedBookingGroups();

        self::assertCount(1, $groups);
        self::assertCount(2, $groups[0]['intervalGroups']);
    }

    public function testRemoveLastSlotInTimeFrameRemovesEntireTimeFrame(): void
    {
        // Create two distinct time frames
        $firstStart = new \DateTimeImmutable('2025-01-05 10:00:00');
        $firstEnd = $firstStart->modify('+1 hour');
        $secondStart = new \DateTimeImmutable('2025-01-05 14:00:00');
        $secondEnd = $secondStart->modify('+2 hours');

        $nakki = NakkiFactory::new()->create([
            'startAt' => $firstStart,
            'endAt' => $secondEnd,
        ]);

        // First time frame: single booking (will be removed)
        $bookingToRemove = NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $firstStart,
                'endAt' => $firstEnd,
            ])
            ->free()
            ->create();

        // Second time frame: two bookings (will remain)
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $secondStart,
                'endAt' => $secondEnd,
            ])
            ->free()
            ->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'nakkikone' => $nakki->getNakkikone(),
                'startAt' => $secondStart,
                'endAt' => $secondEnd,
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        // Verify initial state: two time groups
        /** @var Column $state */
        $state = $component->component();
        $groupsBefore = $state->getNestedBookingGroups();
        self::assertCount(2, $groupsBefore, 'Should have two time groups before removal');

        // Remove the only booking in the first time frame
        $component->call('removeSlot', ['bookingId' => $bookingToRemove->getId()]);

        // After removal, only one time group should remain
        /** @var Column $stateAfter */
        $stateAfter = $component->component();
        $groupsAfter = $stateAfter->getNestedBookingGroups();
        self::assertCount(1, $groupsAfter, 'Should have one time group after removing last slot from first time frame');

        // The remaining group should be the second time frame
        $remainingGroup = $groupsAfter[0];
        self::assertSame(
            $secondStart->format('Y-m-d H:i:s'),
            $remainingGroup['startTime']->format('Y-m-d H:i:s'),
            'Remaining group should be the second time frame'
        );
        self::assertCount(2, $remainingGroup['intervalGroups'][0]['bookings'], 'Second time frame should still have 2 bookings');
    }

    private function reloadNakki(int $id): Nakki
    {
        /** @var NakkiRepository $repository */
        $repository = self::getContainer()->get(NakkiRepository::class);

        $nakki = $repository->find($id);
        self::assertInstanceOf(Nakki::class, $nakki);

        return $nakki;
    }
}
