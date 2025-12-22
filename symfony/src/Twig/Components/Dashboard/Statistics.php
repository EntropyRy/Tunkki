<?php

declare(strict_types=1);

namespace App\Twig\Components\Dashboard;

use App\Repository\BookingRepository;
use App\Repository\EventRepository;
use App\Repository\MemberRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Statistics
{
    public array $stats = [];

    public function __construct(
        private readonly MemberRepository $memberR,
        private readonly BookingRepository $bookingR,
        private readonly EventRepository $eventR,
    ) {
    }

    public function mount(): void
    {
        $this->stats['block.stats.members'] = $this->memberR->countByMember();
        $this->stats['block.stats.active_members'] = $this->memberR->countByActiveMember();
        $this->stats['block.stats.bookings'] = $this->bookingR->countHandled();
        $this->stats['block.stats.events'] = $this->eventR->countDone();
    }
}
