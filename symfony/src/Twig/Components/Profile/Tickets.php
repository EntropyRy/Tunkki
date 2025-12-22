<?php

declare(strict_types=1);

namespace App\Twig\Components\Profile;

use App\Entity\Member;
use App\Repository\TicketRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Tickets
{
    public array $tickets = [];

    public function __construct(
        private readonly TicketRepository $tRepo,
    ) {
    }

    public function mount(Member $member): void
    {
        $this->tickets = $this->tRepo->findMemberTickets($member);
    }
}
