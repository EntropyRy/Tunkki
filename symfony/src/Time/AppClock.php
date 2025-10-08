<?php

declare(strict_types=1);

namespace App\Time;

/**
 * AppClock.
 *
 * Production clock implementation that returns the real current time.
 * Separated into its own file to comply with PSR-4 autoloading and avoid
 * relying on multiple class definitions in a single file.
 */
final class AppClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        // Explicit 'now' for clarity (equivalent to no-arg constructor).
        return new \DateTimeImmutable('now');
    }
}
