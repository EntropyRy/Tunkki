<?php

declare(strict_types=1);

namespace App\Domain\Temporal;

use App\Entity\Member;
use App\Time\ClockInterface;

/**
 * Immutable value object describing ticket presale visibility window.
 */
final readonly class TicketPresaleWindow
{
    public function __construct(
        private bool $ticketsEnabled,
        private ?\DateTimeImmutable $start,
        private ?\DateTimeImmutable $end,
        private ?string $infoFi,
        private ?string $infoEn,
        private bool $membersOnly = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->ticketsEnabled;
    }

    public function isOpen(ClockInterface $clock): bool
    {
        if (!$this->ticketsEnabled) {
            return false;
        }

        if (
            !$this->start instanceof \DateTimeInterface
            || !$this->end instanceof \DateTimeInterface
        ) {
            return false;
        }

        $now = $clock->now();
        $nowS = (int) $now->format('U');
        $startS = (int) $this->start->format('U');
        $endS = (int) $this->end->format('U');

        return $startS <= $nowS && $endS >= $nowS;
    }

    public function canMemberAccess(?Member $member): bool
    {
        if (!$this->ticketsEnabled) {
            return false;
        }

        if (!$this->membersOnly) {
            return true;
        }

        return null !== $member;
    }

    public function getInfoByLocale(string $locale): ?string
    {
        return 'en' === $locale ? $this->infoEn : $this->infoFi;
    }
}
