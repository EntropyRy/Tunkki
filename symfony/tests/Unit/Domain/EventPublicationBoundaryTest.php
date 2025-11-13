<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Time\MutableClock;
use PHPUnit\Framework\TestCase;

/**
 * EventPublicationBoundaryTest.
 *
 * Temporal mutation harness for EventTemporalStateService boundary logic.
 *
 * Goals:
 *  - Assert correct classification around publishDate boundaries (just before, exactly at, just after).
 *  - Kill conditional mutants (>, >=, <, <= inversions) produced by mutation testing tools.
 *  - Provide explicit semantic coverage for “draft” (null date / unpublished), “scheduled” (future), and “live” (past / boundary) states.
 *
 * Strategy:
 *  - Use a MutableClock so we can freeze & advance simulated time deterministically.
 *  - Construct minimal Event entities directly (factories not required for pure unit domain coverage).
 *  - Validate idempotence: state remains consistent after multiple isPublished() evaluations.
 */
final class EventPublicationBoundaryTest extends TestCase
{
    private MutableClock $clock;
    private EventTemporalStateService $decider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new MutableClock('2025-01-01T12:00:00+00:00');
        $this->decider = new EventTemporalStateService($this->clock);
    }

    public function testDraftWhenUnpublishedAndNullPublishDate(): void
    {
        $event = $this->newEvent(false, null);

        self::assertFalse(
            $this->decider->isPublished($event),
            'Unpublished event with null publishDate must be draft (not published).'
        );
    }

    public function testNotPublishedWhenFlagFalseEvenIfPastPublishDate(): void
    {
        $event = $this->newEvent(false, $this->clock->now()->modify('-1 day'));
        self::assertFalse(
            $this->decider->isPublished($event),
            'publishDate in past should not override explicit published=false flag.'
        );
    }

    public function testScheduledWhenPublishedFlagTrueButPublishDateInFuture(): void
    {
        $event = $this->newEvent(true, $this->clock->now()->modify('+1 hour'));
        self::assertFalse(
            $this->decider->isPublished($event),
            'Published flag true with future publishDate => scheduled, not yet live.'
        );
    }

    public function testLiveWhenPublishDateInPastAndPublishedFlagTrue(): void
    {
        $event = $this->newEvent(true, $this->clock->now()->modify('-5 minutes'));
        self::assertTrue(
            $this->decider->isPublished($event),
            'Published flag true with past publishDate => live.'
        );
    }

    public function testBoundaryExactlyAtPublishDateIsConsideredLive(): void
    {
        $boundary = $this->clock->now();
        $event = $this->newEvent(true, $boundary);

        self::assertTrue(
            $this->decider->isPublished($event),
            'publishDate exactly equal to now should be considered live (>= boundary).'
        );
    }

    public function testJustBeforeBoundaryNotYetLive(): void
    {
        $publishDate = $this->clock->now()->modify('+10 seconds'); // event scheduled 10s ahead
        $event = $this->newEvent(true, $publishDate);

        // Advance to 1 second before boundary.
        $this->clock->setNow($publishDate->modify('-1 second'));

        self::assertFalse(
            $this->decider->isPublished($event),
            'One second before publish boundary must still be scheduled.'
        );
    }

    public function testAtAndAfterBoundaryTransitionToLive(): void
    {
        $publishDate = $this->clock->now()->modify('+30 minutes');
        $event = $this->newEvent(true, $publishDate);

        // At boundary
        $this->clock->setNow($publishDate);
        self::assertTrue(
            $this->decider->isPublished($event),
            'At publish boundary instant the event should become live.'
        );

        // After boundary
        $this->clock->advance('+1 second');
        self::assertTrue(
            $this->decider->isPublished($event),
            'After boundary the event must remain live.'
        );
    }

    public function testMultipleEvaluationsDoNotMutateEventState(): void
    {
        $publishDate = $this->clock->now()->modify('-1 hour');
        $event = $this->newEvent(true, $publishDate);

        $first = $this->decider->isPublished($event);
        $second = $this->decider->isPublished($event);

        self::assertTrue($first);
        self::assertTrue($second);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoundaryScenarios')]
    public function testBoundaryScenarios(
        string $initialClock,
        bool $publishedFlag,
        ?string $publishDate,
        string $advance,
        bool $expected,
    ): void {
        $this->clock->setNow($initialClock);
        $event = $this->newEvent(
            $publishedFlag,
            $publishDate ? new \DateTimeImmutable($publishDate) : null
        );

        if ('' !== $advance) {
            $this->clock->advance($advance);
        }

        $result = $this->decider->isPublished($event);

        $context = \sprintf(
            '[initial=%s flag=%s publishDate=%s advance=%s]',
            $initialClock,
            $publishedFlag ? 'true' : 'false',
            $publishDate ?? 'null',
            $advance
        );

        self::assertSame(
            $expected,
            $result,
            "Boundary scenario expectation mismatch {$context}"
        );
    }

    /**
     * Data provider intentionally targets edge cases to kill mutants:
     *  - publishDate == now (>= vs >)
     *  - publishDate just before now (< vs <=)
     *  - future vs past detection
     *  - null publishDate drafts
     *  - published flag false dominance
     *
     * Columns:
     *  [initialClock, publishedFlag, publishDate|null, advance (relative), expectedIsPublished]
     *
     * NOTE: Use small deltas (±1 second) to ensure deterministic boundary assertions.
     *
     * @return iterable<array{string,bool,?string,string,bool}>
     */
    public static function provideBoundaryScenarios(): iterable
    {
        yield 'draft-null-date-unpublished' => [
            '2025-01-01T12:00:00+00:00',
            false,
            null,
            '',
            false,
        ];
        yield 'scheduled-2s-ahead' => [
            '2025-01-01T12:00:00+00:00',
            true,
            '2025-01-01T12:00:02+00:00',
            '',
            false,
        ];
        yield 'scheduled-just-before-boundary' => [
            '2025-01-01T11:59:59+00:00',
            true,
            '2025-01-01T12:00:00+00:00',
            '',
            false,
        ];
        yield 'live-at-boundary' => [
            '2025-01-01T12:00:00+00:00',
            true,
            '2025-01-01T12:00:00+00:00',
            '',
            true,
        ];
        yield 'live-after-boundary-1s' => [
            '2025-01-01T11:59:59+00:00',
            true,
            '2025-01-01T12:00:00+00:00',
            '+1 second',
            true,
        ];
        yield 'live-past-date' => [
            '2025-01-01T12:05:00+00:00',
            true,
            '2025-01-01T12:00:00+00:00',
            '',
            true,
        ];
        yield 'flag-false-dominates-past' => [
            '2025-01-01T12:05:00+00:00',
            false,
            '2025-01-01T12:00:00+00:00',
            '',
            false,
        ];
        yield 'flag-false-dominates-boundary' => [
            '2025-01-01T12:00:00+00:00',
            false,
            '2025-01-01T12:00:00+00:00',
            '',
            false,
        ];
        yield 'live-after-advancing-to-boundary' => [
            '2025-01-01T11:59:50+00:00',
            true,
            '2025-01-01T12:00:00+00:00',
            '+10 seconds',
            true,
        ];
        yield 'future-after-advancing-past-boundary' => [
            '2025-01-01T11:59:50+00:00',
            true,
            '2025-01-01T12:00:00+00:00',
            '+10 minutes',
            true,
        ];
    }

    /**
     * Create a minimal Event suitable for the decider.
     *
     * NOTE: We purposefully only touch properties required for publication logic to keep
     * the test focused and resilient to unrelated entity changes.
     */
    private function newEvent(bool $publishedFlag, ?\DateTimeImmutable $publishDate): Event
    {
        $event = new Event();
        // Simplified: entity provides explicit setters; avoid private property access fallbacks.
        $event->setPublished($publishedFlag);
        $event->setPublishDate($publishDate);

        return $event;
    }
}
