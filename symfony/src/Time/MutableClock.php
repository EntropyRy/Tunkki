<?php

declare(strict_types=1);

namespace App\Time;

/**
 * MutableClock.
 *
 * A controllable implementation of ClockInterface intended for tests
 * and controlled simulation scenarios. It allows explicitly setting or
 * advancing the "current" instant without relying on the real system time.
 *
 * Rationale:
 *  - Facilitates deterministic testing of temporal boundary conditions
 *    (e.g. publish windows, signup cutoffs, expiry).
 *  - Enables mutation testing to meaningfully exercise inverted or
 *    off‑by‑one temporal predicates by advancing time between assertions.
 *  - Avoids scattering sleep() or ad hoc new DateTimeImmutable() calls
 *    across tests, which introduce flakiness and nondeterminism.
 *
 * Usage (example in a functional / integration test):
 *
 *   /** @var MutableClock $clock *\/
 *   $clock = static::getContainer()->get(\App\Time\ClockInterface::class);
 *   $this->assertTrue($decider->isPublished($event));
 *   $clock->advance('+2 hours');
 *   $this->assertFalse($decider->isPublished($eventScheduledLater));
 *
 * Or using convenience helpers:
 *
 *   $clock->advanceSeconds(30);
 *   $clock->advanceMinutes(5);
 *
 * Implementation Notes:
 *  - The internal instant is always a DateTimeImmutable (immutability
 *    avoids accidental in-place mutation side effects).
 *  - Convenience advance* methods wrap ::advance() for readability.
 *  - Relative modifiers delegate to DateTimeImmutable::modify(); invalid
 *    modifiers will trigger an InvalidArgumentException for early feedback.
 *
 * Thread Safety:
 *  - This class is NOT thread safe. In parallel test runners isolate
 *    container instances per process. Do not share a single MutableClock
 *    instance across concurrent threads without synchronization.
 *
 * Production Guidance:
 *  - Do NOT register MutableClock as the production ClockInterface. Use
 *    AppClock (real time) in non-test environments to avoid semantic drift.
 *
 * @internal intended primarily for test environment usage (when@test)
 */
final class MutableClock implements ClockInterface
{
    /**
     * The internally tracked "now" instant.
     */
    private \DateTimeImmutable $now;

    /**
     * @param \DateTimeImmutable|string|null $initial
     *                                                - \DateTimeImmutable: adopted directly
     *                                                - string: parsed via new DateTimeImmutable($string)
     *                                                - null: defaults to real current time at construction
     */
    public function __construct(\DateTimeImmutable|string|null $initial = null)
    {
        $this->now = $this->normalizeInitial($initial);
    }

    /**
     * Return the current simulated instant.
     */
    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Overwrite the current instant.
     */
    public function setNow(\DateTimeImmutable|string $new): void
    {
        $this->now = $new instanceof \DateTimeImmutable
            ? $new
            : $this->parseStringInstant($new);
    }

    /**
     * Advance (or rewind) time using a relative modifier expression
     * understood by DateTimeImmutable::modify().
     *
     * Examples:
     *   $clock->advance('+15 minutes');
     *   $clock->advance('-2 days');
     *   $clock->advance('next monday');
     *
     * @throws \InvalidArgumentException if the modifier is invalid
     */
    public function advance(string $modifier): void
    {
        $modified = $this->now->modify($modifier);
        if (false === $modified) {
            throw new \InvalidArgumentException(\sprintf('MutableClock::advance(): Invalid relative time modifier "%s".', $modifier));
        }
        $this->now = $modified;
    }

    /**
     * Advance forward by a number of seconds (negative values allowed to rewind).
     */
    public function advanceSeconds(int $seconds): void
    {
        if (0 === $seconds) {
            return;
        }
        $sign = $seconds >= 0 ? '+' : '';
        $this->advance(\sprintf('%s%d seconds', $sign, $seconds));
    }

    /**
     * Advance forward by a number of minutes (negative to rewind).
     */
    public function advanceMinutes(int $minutes): void
    {
        if (0 === $minutes) {
            return;
        }
        $sign = $minutes >= 0 ? '+' : '';
        $this->advance(\sprintf('%s%d minutes', $sign, $minutes));
    }

    /**
     * Advance forward by a number of hours (negative to rewind).
     */
    public function advanceHours(int $hours): void
    {
        if (0 === $hours) {
            return;
        }
        $sign = $hours >= 0 ? '+' : '';
        $this->advance(\sprintf('%s%d hours', $sign, $hours));
    }

    /**
     * Advance forward by a number of days (negative to rewind).
     */
    public function advanceDays(int $days): void
    {
        if (0 === $days) {
            return;
        }
        $sign = $days >= 0 ? '+' : '';
        $this->advance(\sprintf('%s%d days', $sign, $days));
    }

    /**
     * Clone-safe: ensure new instances have isolated state.
     */
    public function __clone()
    {
        // DateTimeImmutable is itself immutable; cloning preserves $now semantics intentionally.
    }

    /**
     * Normalize constructor argument into a DateTimeImmutable.
     */
    private function normalizeInitial(\DateTimeImmutable|string|null $initial): \DateTimeImmutable
    {
        if ($initial instanceof \DateTimeImmutable) {
            return $initial;
        }
        if (\is_string($initial)) {
            return $this->parseStringInstant($initial);
        }

        return new \DateTimeImmutable('now');
    }

    /**
     * Parse a string into a DateTimeImmutable with explicit error signaling.
     *
     * @throws \InvalidArgumentException
     */
    private function parseStringInstant(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(\sprintf('MutableClock: Unable to parse datetime string "%s": %s', $value, $e->getMessage()), $e->getCode(), previous: $e);
        }
    }
}
