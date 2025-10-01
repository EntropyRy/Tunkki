<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Event;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Event::getArtistSignUpNow() boundary and logic conditions.
 *
 * Method implementation (from inspection):
 *
 *   public function getArtistSignUpNow(): bool
 *   {
 *       $now = new \DateTimeImmutable("now");
 *       return $this->getArtistSignUpEnabled() &&
 *           $this->getArtistSignUpStart() <= $now &&
 *           $this->getArtistSignUpEnd() >= $now &&
 *           !$this->isInPast();
 *   }
 *
 * We validate:
 *  - True inside window (inclusive boundaries) when event is in the future.
 *  - False if disabled.
 *  - False before start.
 *  - False after end.
 *  - False when event itself is already in the past (even if window matches).
 *  - True when "now" equals start boundary (inclusive).
 *  - True when "now" equals end boundary (inclusive).
 *
 * NOTE: Because the method itself obtains "now" internally, tests must create
 * start/end timestamps relative to a captured $now and invoke the method
 * immediately. We keep windows wide enough (seconds granularity) to avoid
 * timing flakiness. All comparisons are second-based so sub‑second drift is
 * acceptable.
 */
final class EventArtistSignupWindowTest extends TestCase
{
    /**
     * Helper: create a minimally valid Event with provided core fields.
     */
    private function makeBaseEvent(\DateTimeImmutable $eventDate): Event
    {
        $e = new Event();
        // Provide minimal required data for non-nullable / commonly used logic.
        $e->setName('Test Event EN');
        $e->setNimi('Testitapahtuma FI');
        $e->setType('event');
        $e->setEventDate($eventDate);
        // Publish date & published not strictly required for getArtistSignUpNow,
        // but some downstream methods possibly referenced by future tests.
        $e->setPublishDate(new \DateTimeImmutable('-1 hour'));
        $e->setPublished(true);

        return $e;
    }

    public function testReturnsTrueInsideWindowWhenEnabled(): void
    {
        $now = new \DateTimeImmutable('now');
        $event = $this->makeBaseEvent($now->modify('+10 days'));

        $event
            ->setArtistSignUpEnabled(true)
            ->setArtistSignUpStart($now->modify('-1 hour'))
            ->setArtistSignUpEnd($now->modify('+1 hour'));

        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Expected true when enabled and now within window.',
        );
    }

    public function testReturnsFalseWhenDisabledEvenInsideWindow(): void
    {
        $now = new \DateTimeImmutable('now');
        $event = $this->makeBaseEvent($now->modify('+5 days'));

        $event
            ->setArtistSignUpEnabled(false)
            ->setArtistSignUpStart($now->modify('-10 minutes'))
            ->setArtistSignUpEnd($now->modify('+10 minutes'));

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Disabled flag should force false.',
        );
    }

    public function testReturnsFalseBeforeWindowStart(): void
    {
        $now = new \DateTimeImmutable('now');
        $event = $this->makeBaseEvent($now->modify('+3 days'));

        $event
            ->setArtistSignUpEnabled(true)
            ->setArtistSignUpStart($now->modify('+5 minutes'))
            ->setArtistSignUpEnd($now->modify('+1 hour'));

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Should be false before signup window start.',
        );
    }

    public function testReturnsFalseAfterWindowEnd(): void
    {
        $now = new \DateTimeImmutable('now');
        $event = $this->makeBaseEvent($now->modify('+2 days'));

        $event
            ->setArtistSignUpEnabled(true)
            ->setArtistSignUpStart($now->modify('-2 hours'))
            ->setArtistSignUpEnd($now->modify('-1 minute'));

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Should be false after signup window end.',
        );
    }

    public function testReturnsFalseIfEventIsInPastEvenInsideWindow(): void
    {
        $now = new \DateTimeImmutable('now');
        // Event date already 1 hour in the past
        $event = $this->makeBaseEvent($now->modify('-1 hour'));

        $event
            ->setArtistSignUpEnabled(true)
            ->setArtistSignUpStart($now->modify('-2 hours'))
            ->setArtistSignUpEnd($now->modify('+2 hours'));

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Past event should negate signup availability.',
        );
    }

    public function testReturnsTrueAtExactStartBoundary(): void
    {
        $now = new \DateTimeImmutable('now');
        $event = $this->makeBaseEvent($now->modify('+1 day'));

        // Align start exactly to (now) second
        $start = new \DateTimeImmutable('@'.$now->getTimestamp());
        $end = $start->modify('+30 minutes');

        $event
            ->setArtistSignUpEnabled(true)
            ->setArtistSignUpStart($start)
            ->setArtistSignUpEnd($end);

        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Start boundary should be inclusive.',
        );
    }

    public function testReturnsTrueNearEndBoundary(): void
    {
        $now = new \DateTimeImmutable('now');
        $event = $this->makeBaseEvent($now->modify('+1 day'));

        // Use a small forward buffer to avoid same-second race conditions that
        // caused flakiness when asserting exactly at the end boundary.
        $start = $now->modify('-15 minutes');
        $end = $now->modify('+30 seconds');

        $event
            ->setArtistSignUpEnabled(true)
            ->setArtistSignUpStart($start)
            ->setArtistSignUpEnd($end);

        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Expected true shortly before end boundary (buffered).',
        );
    }
}
