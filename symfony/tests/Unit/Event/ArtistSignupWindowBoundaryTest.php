<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event;

use App\Entity\Event;
use PHPUnit\Framework\TestCase;

/**
 * ArtistSignupWindowBoundaryTest.
 *
 * Purpose:
 *   Unit-level verification of boundary logic in Event::getArtistSignUpNow()
 *   (without booting the Symfony kernel or relying on factories).
 *
 * Covered Roadmap Items:
 *   - Negative / boundary path coverage (Tasks #20, #21, C).
 *   - Temporal boundary hardening (Temporal Addendum TA5 / TA17).
 *   - Pre-mutation-defense tests for conditional flips in signup window logic.
 *
 * Why unit (not functional):
 *   Faster feedback, deterministic "now" captured once per test.
 *   Reduces chance of escaped mutation (>= vs > or reversed condition).
 *
 * NOTE:
 *   The Event entity currently performs time evaluation internally via
 *   new \DateTimeImmutable('now') (and related logic in isInPast()).
 *   We simulate conditions by setting internal datetime properties
 *   relative to a fixed $now value and asserting expected boolean results.
 *
 * Implementation Detail:
 *   Because Event uses private properties (with some capitalized names
 *   like $EventDate), we set them via Reflection to avoid depending on
 *   potentially absent / side-effectful setters. If setters are preferred
 *   later, this test can be refactored to call them directly.
 */
final class ArtistSignupWindowBoundaryTest extends TestCase
{
    /**
     * Helper to build an Event with specified signup window and event dates.
     *
     * @param \DateTimeImmutable      $now       Reference "current" instant for relative calculations
     * @param bool                    $enabled   artistSignUpEnabled flag
     * @param \DateTimeImmutable|null $start     signup window start
     * @param \DateTimeImmutable|null $end       signup window end
     * @param \DateTimeImmutable|null $eventDate main event date/time
     * @param \DateTimeImmutable|null $until     multiday end (optional)
     */
    private function buildEvent(
        \DateTimeImmutable $now,
        bool $enabled,
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
        ?\DateTimeImmutable $eventDate,
        ?\DateTimeImmutable $until = null,
    ): Event {
        $event = new Event();

        $this->setPrivate($event, 'artistSignUpEnabled', $enabled);
        $this->setPrivate($event, 'artistSignUpStart', $start);
        $this->setPrivate($event, 'artistSignUpEnd', $end);

        // Event temporal context (affects isInPast() via getNowTest()).
        if (null !== $eventDate) {
            $this->setPrivate($event, 'EventDate', $eventDate);
        }
        if (null !== $until) {
            $this->setPrivate($event, 'until', $until);
        }

        // Safety: ensure publishDate null (draft semantics) not required for these tests.
        // If domain later enforces publishDate != null, adapt accordingly.

        return $event;
    }

    /**
     * Convenience reflection setter (no exception swallowed).
     */
    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionClass($object);
        if (!$ref->hasProperty($property)) {
            $this->fail("Property '{$property}' not found on ".$object::class);
        }
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    public function testWindowOpenInsideInterval(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now->modify('-5 minutes'),
            end: $now->modify('+5 minutes'),
            eventDate: $now->modify('+1 day'),
        );

        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Expected true when now is strictly inside the enabled signup window and event is in the future.'
        );
    }

    public function testWindowFalseWhenDisabledFlag(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: false,
            start: $now->modify('-10 minutes'),
            end: $now->modify('+10 minutes'),
            eventDate: $now->modify('+2 days'),
        );

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Expected false when flag is disabled even if window would otherwise include now.'
        );
    }

    public function testWindowFalseBeforeStartBoundary(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now->modify('+1 minute'),      // start in the future
            end: $now->modify('+10 minutes'),
            eventDate: $now->modify('+1 day'),
        );

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Expected false when current time is before signup start.'
        );
    }

    public function testWindowTrueAtExactStartBoundary(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now,                           // boundary inclusive
            end: $now->modify('+30 minutes'),
            eventDate: $now->modify('+2 days'),
        );

        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Expected true when now equals the start boundary (inclusive).'
        );
    }

    public function testWindowTrueAtExactEndBoundary(): void
    {
        $now = new \DateTimeImmutable();
        $end = $now; // inclusive boundary check
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now->modify('-30 minutes'),
            end: $end,
            eventDate: $now->modify('+3 days'),
        );

        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Expected true when now equals the end boundary if end comparison is inclusive.'
        );
    }

    public function testWindowFalseAfterEndBoundary(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now->modify('-10 minutes'),
            end: $now->modify('-1 second'),
            eventDate: $now->modify('+1 day'),
        );

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Expected false when current time is strictly after signup end.'
        );
    }

    public function testWindowFalseWhenEventInPastEvenIfIntervalMatches(): void
    {
        $now = new \DateTimeImmutable();
        // Event already in past => isInPast() true should force result false.
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now->modify('-5 minutes'),
            end: $now->modify('+5 minutes'),
            eventDate: $now->modify('-1 day'),
        );

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Expected false when event is in the past even if window would appear open.'
        );
    }

    public function testWindowFalseWhenStartNull(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: null,
            end: $now->modify('+10 minutes'),
            eventDate: $now->modify('+2 days'),
        );

        // Because getArtistSignUpStart() <= now will likely be null comparison (should fail)
        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Expected false when start boundary is null (incomplete window definition).'
        );
    }

    public function testWindowFalseWhenEndNull(): void
    {
        $now = new \DateTimeImmutable();
        $event = $this->buildEvent(
            $now,
            enabled: true,
            start: $now->modify('-10 minutes'),
            end: null,
            eventDate: $now->modify('+1 day'),
        );

        self::assertFalse(
            $event->getArtistSignUpNow(),
            'Expected false when end boundary is null (incomplete window definition).'
        );
    }
}
