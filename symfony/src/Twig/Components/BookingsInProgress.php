<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\BookingRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class BookingsInProgress
{
    public bool $box = false;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    public function mount(bool $box = false): void
    {
        $this->box = $box;
        $this->bookings = $this->bookingRepository->findBy([
            'itemsReturned' => false,
            'cancelled' => false,
        ], [
            'bookingDate' => 'DESC',
        ]);
    }
}
