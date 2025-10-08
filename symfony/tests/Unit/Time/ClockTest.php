<?php

declare(strict_types=1);

namespace App\Tests\Unit\Time;

use App\Time\AppClock;
use App\Time\ClockInterface;
use App\Time\FixedClock;
use App\Time\MutableClock;
use PHPUnit\Framework\TestCase;

/**
 * @group time
 *
 * Basic contract & behavior tests for the in‑house clock abstractions.
 *
 * These tests intentionally avoid asserting on exact real system time
 * (which would introduce flakiness) and instead validate:
 *  - Interface conformance
 *  - Immutability of returned instances
 *  - Deterministic behavior of FixedClock
 *  - Controlled mutability and relative advancement of MutableClock
 */
final class ClockTest extends TestCase
{
    public function testAppClockReturnsCurrentTime(): void
    {
        $clock = new AppClock();

        $t1 = $clock->now();
        $this->assertInstanceOf(\DateTimeImmutable::class, $t1);

        // A short delay to ensure second-level change *may* occur; if not, we still accept same-second values.
        usleep(50_000);

        $t2 = $clock->now();

        $this->assertInstanceOf(\DateTimeImmutable::class, $t2);
        $this->assertNotSame($t1, $t2, 'AppClock must return a new immutable instance each call.');

        // Either same timestamp (very fast) or later — never earlier.
        $this->assertGreaterThanOrEqual(
            $t1->getTimestamp(),
            $t2->getTimestamp(),
            'AppClock returned a time earlier than a prior call (unexpected).'
        );
    }

    public function testFixedClockAlwaysReturnsSameInstant(): void
    {
        $fixedInstant = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $clock = new FixedClock($fixedInstant);

        $a = $clock->now();
        $b = $clock->now();

        $this->assertInstanceOf(\DateTimeImmutable::class, $a);
        $this->assertSame($fixedInstant->getTimestamp(), $a->getTimestamp());
        $this->assertSame(
            $a->format(\DateTimeInterface::ATOM),
            $b->format(\DateTimeInterface::ATOM),
            'FixedClock returned a different instant than expected.'
        );
    }

    public function testFixedClockFromStringParsesInstant(): void
    {
        $clock = new FixedClock('2030-12-31T23:59:59+00:00');
        $now = $clock->now();

        $this->assertSame('2030-12-31T23:59:59+00:00', $now->format('Y-m-d\TH:i:sP'));
    }

    public function testMutableClockInitialDefaultsToRealNow(): void
    {
        $before = new \DateTimeImmutable('now');
        $clock = new MutableClock();
        $after = new \DateTimeImmutable('now');

        $now = $clock->now();

        // Basic window containment (not asserting exact equality).
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function testMutableClockSetNow(): void
    {
        $clock = new MutableClock('2025-01-01T00:00:00+00:00');

        $this->assertSame('2025-01-01T00:00:00+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));

        $clock->setNow('2026-06-15T08:30:00+00:00');
        $this->assertSame('2026-06-15T08:30:00+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));
    }

    public function testMutableClockAdvanceRelative(): void
    {
        $clock = new MutableClock('2025-01-01T00:00:00+00:00');

        $clock->advance('+1 day');
        $this->assertSame('2025-01-02T00:00:00+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));

        $clock->advance('-2 hours'); // Rewinds 2 hours from midnight (previous day 22:00)
        $this->assertSame('2025-01-01T22:00:00+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));
    }

    public function testMutableClockConvenienceAdvanceMethods(): void
    {
        $clock = new MutableClock('2025-01-01T00:00:00+00:00');

        $clock->advanceSeconds(30);
        $this->assertSame('2025-01-01T00:00:30+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));

        $clock->advanceMinutes(2);
        $this->assertSame('2025-01-01T00:02:30+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));

        $clock->advanceHours(1);
        $this->assertSame('2025-01-01T01:02:30+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));

        $clock->advanceDays(1);
        $this->assertSame('2025-01-02T01:02:30+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));

        // Rewind using negative values
        $clock->advanceSeconds(-30);
        $this->assertSame('2025-01-02T01:02:00+00:00', $clock->now()->format('Y-m-d\TH:i:sP'));
    }

    public function testMutableClockAdvanceInvalidModifierThrows(): void
    {
        $clock = new MutableClock('2025-01-01T00:00:00+00:00');

        // DateTimeImmutable::modify() may throw a \DateMalformedStringException in newer PHP versions
        // before returning false (which our MutableClock would then convert to InvalidArgumentException).
        // Accept either to keep the test stable across minor engine changes.
        try {
            $clock->advance('++ invalid relative spec ++');
            $this->fail('Expected an exception for invalid relative time modifier.');
        } catch (\InvalidArgumentException|\DateMalformedStringException $e) {
            $this->assertStringContainsString(
                'invalid',
                strtolower($e->getMessage()),
                'Exception message should reference invalid modifier.'
            );
        }
    }

    public function testAllClocksImplementInterface(): void
    {
        $this->assertInstanceOf(ClockInterface::class, new AppClock());
        $this->assertInstanceOf(ClockInterface::class, new FixedClock('2025-01-01T00:00:00+00:00'));
        $this->assertInstanceOf(ClockInterface::class, new MutableClock());
    }
}
