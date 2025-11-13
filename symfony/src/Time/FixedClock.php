<?php

declare(strict_types=1);

namespace App\Time;

/**
 * FixedClock.
 *
 * Deterministic clock implementation for tests. It always returns the same
 * immutable instant supplied at construction time (or the default "epoch"
 * you choose). This enables precise verification of time‑dependent logic and
 * easy mutation testing of temporal branches.
 *
 * Typical usage (service override with when@test in services.yaml):
 *
 * when@test:
 *   services:
 *     App\Time\ClockInterface:
 *       class: App\Time\FixedClock
 *       arguments:
 *         - '2025-01-01T12:00:00+00:00'
 *
 * In a test you can fetch the clock or replace it:
 *
 *   /** @var FixedClock $clock *\/
 *   $clock = static::getContainer()->get(App\Time\ClockInterface::class);
 *   // If you want a different instant for a specific test:
 *   $reflected = new \ReflectionProperty($clock, 'now');
 *   $reflected->setAccessible(true);
 *   $reflected->setValue($clock, new \DateTimeImmutable('2030-12-31T23:59:59+00:00'));
 *
 * (For cleaner mutation, prefer a MutableTestClock variant if frequent time
 * shifts are required; this implementation stays intentionally minimal.)
 */
final class FixedClock implements ClockInterface
{
    /**
     * Internal stored instant.
     */
    private \DateTimeImmutable $now;

    /**
     * @param \DateTimeImmutable|string|null $fixed Instant to fix "now" at.
     *                                              - If string, it is parsed via new DateTimeImmutable($string)
     *                                              - If null, defaults to current real-time "now" at construction.
     */
    public function __construct(\DateTimeImmutable|string|null $fixed = null)
    {
        if ($fixed instanceof \DateTimeImmutable) {
            $this->now = $fixed;
        } elseif (\is_string($fixed)) {
            $dt = new \DateTimeImmutable($fixed);
            $this->now = $dt;
        } else {
            $this->now = new \DateTimeImmutable('now');
        }
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * (Optional helper) Returns a new FixedClock advanced by a relative modifier.
     * Leaves the original clock unchanged.
     *
     * Example:
     *   $futureClock = $clock->advanced('+2 hours');
     */
    public function advanced(string $modifier): self
    {
        return new self($this->now->modify($modifier));
    }

    /**
     * (Optional helper) Produces a cloned clock with an absolute new instant.
     * Useful when you want to simulate a jump without mutating existing references.
     */
    public function at(string|\DateTimeImmutable $instant): self
    {
        return new self($instant);
    }
}

/*
-------------------------------------------------------------------------------
Optional Mutable Variant (Not Registered by Default)
-------------------------------------------------------------------------------

If tests frequently need to "advance" time, consider adding a separate class:

final class MutableTestClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $current) {}

    public function now(): \DateTimeImmutable { return $this->current; }

    public function advance(string $modifier): void
    {
        $this->current = $this->current->modify($modifier);
    }
}

Register under when@test only if needed; avoid overcomplicating until a real
temporal test scenario demands incremental advancement.

-------------------------------------------------------------------------------
Migration Guidance
-------------------------------------------------------------------------------
1. Keep using App\Time\AppClock in production (real time).
2. Override App\Time\ClockInterface with FixedClock in tests (when@test).
3. Refactor services (NOT raw entities) to depend on ClockInterface.
4. Move temporal business logic out of entities into dedicated domain services
   that inject the clock (e.g. EventTemporalStateService).
5. Add mutation tests for boundary conditions using FixedClock->advanced()
   or by constructing new FixedClock instances.

-------------------------------------------------------------------------------
*/
