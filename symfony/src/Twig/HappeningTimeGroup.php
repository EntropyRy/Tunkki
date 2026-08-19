<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Happening;

final readonly class HappeningTimeGroup
{
    /**
     * @param list<Happening> $happenings
     */
    public function __construct(
        public \DateTimeImmutable $time,
        public array $happenings,
    ) {
    }
}
