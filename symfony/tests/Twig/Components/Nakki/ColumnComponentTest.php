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

    public function testRemoveSlotDeletesUnreservedBooking(): void
    {
        $nakki = NakkiFactory::new()->create();
        $booking = NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'event' => $nakki->getEvent(),
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
                'event' => $nakki->getEvent(),
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

    public function testDeleteColumnPreventsWhenReserved(): void
    {
        $nakki = NakkiFactory::new()->create();
        NakkiBookingFactory::new()
            ->with([
                'nakki' => $nakki,
                'event' => $nakki->getEvent(),
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
                'event' => $nakki->getEvent(),
            ])
            ->free()
            ->create();

        $component = $this->mountComponent(Column::class, ['nakkiId' => $nakki->getId()]);
        $component->render();

        $component->call('deleteColumn');

        $repository = self::getContainer()->get(NakkiRepository::class);
        self::assertNull($repository->find($nakki->getId()));
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
