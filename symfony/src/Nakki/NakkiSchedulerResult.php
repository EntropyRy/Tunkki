<?php

declare(strict_types=1);

namespace App\Nakki;

use App\Entity\NakkiBooking;

/**
 * Value object describing the outcome of a scheduling operation.
 *
 * @immutable
 */
final class NakkiSchedulerResult
{
    /**
     * @param list<NakkiBooking> $created
     * @param list<NakkiBooking> $removed
     * @param list<NakkiBooking> $preserved
     * @param list<NakkiBooking> $conflicts
     */
    public function __construct(
        public readonly array $created = [],
        public readonly array $removed = [],
        public readonly array $preserved = [],
        public readonly array $conflicts = [],
        public readonly ?string $warning = null,
    ) {
    }

    public function hasConflicts(): bool
    {
        return [] !== $this->conflicts;
    }

    public function hasChanges(): bool
    {
        return [] !== $this->created || [] !== $this->removed;
    }
}
