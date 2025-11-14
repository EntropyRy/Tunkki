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

    private function reloadNakki(int $id): Nakki
    {
        /** @var NakkiRepository $repository */
        $repository = self::getContainer()->get(NakkiRepository::class);

        $nakki = $repository->find($id);
        self::assertInstanceOf(Nakki::class, $nakki);

        return $nakki;
    }
}
