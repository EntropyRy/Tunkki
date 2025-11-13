<?php

declare(strict_types=1);

namespace App\Domain\Temporal;

use App\Entity\Member;
use App\Time\ClockInterface;

/**
 * Immutable value object describing artist signup window boundaries.
 *
 * Holds only scalar/DateTime state derived from Event so tests can focus on
 * boundary logic without touching the entity or persistence layer.
 */
final readonly class ArtistSignupWindow
{
    public function __construct(
        private bool $enabled,
        private ?\DateTimeImmutable $start,
        private ?\DateTimeImmutable $end,
        private bool $membersOnly,
        private ?string $infoFi,
        private ?string $infoEn,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function requiresAuthentication(): bool
    {
        return $this->membersOnly;
    }

    public function isOpen(ClockInterface $clock): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $start = $this->start;
        $end = $this->end;

        if (
            !$start instanceof \DateTimeInterface
            || !$end instanceof \DateTimeInterface
        ) {
            return false;
        }

        $now = $clock->now();

        return $this->toSecond($start) <= $this->toSecond($now)
            && $this->toSecond($end) >= $this->toSecond($now);
    }

    public function canMemberAccess(?Member $member): bool
    {
        if (!$this->enabled) {
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

    /**
     * Normalizes DateTimeInterface to integer seconds to avoid microsecond drift.
     */
    private function toSecond(\DateTimeInterface $value): int
    {
        return (int) $value->format('U');
    }
}
