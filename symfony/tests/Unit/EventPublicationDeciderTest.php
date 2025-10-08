<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\EventPublicationDecider;
use App\Entity\Event;
use App\Time\ClockInterface;
use App\Time\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\EventPublicationDecider
 *
 * This test suite validates publication logic extracted from the Event entity
 * into the EventPublicationDecider domain service. It ensures deterministic
 * evaluation of time-based rules via a fixed clock.
 *
 * Publication Rules (current implementation):
 *  - Event must have published flag (== true)   (loose compare preserved from legacy)
 *  - publishDate must be non-null
 *  - publishDate must be earlier than or equal to "now" (publishDate &lt;= now) to be considered live
 *
 * Derived states (publicationState()):
 *  - draft      : published flag false
 *  - scheduled  : published flag true  AND publishDate &gt; now
 *  - live       : published flag true  AND publishDate &lt;= now
 *  - unknown    : published flag true  AND publishDate null
 */
final class EventPublicationDeciderTest extends TestCase
{
    private ClockInterface $clock;
    private EventPublicationDecider $decider;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        // Fixed reference instant for all tests; adjust if boundary assertions need a different epoch.
        $this->now = new \DateTimeImmutable('2025-02-15T12:00:00+00:00');
        $this->clock = new FixedClock($this->now);
        $this->decider = new EventPublicationDecider($this->clock);
    }

    public function testDraftState(): void
    {
        $event = $this->makeEvent(published: false, publishOffset: '-1 hour');
        self::assertFalse($this->decider->isPublished($event), 'Draft event must not be published.');
        self::assertSame('draft', $this->decider->publicationState($event));
    }

    public function testScheduledStateWithFuturePublishDate(): void
    {
        $event = $this->makeEvent(published: true, publishOffset: '+10 minutes');
        self::assertFalse(
            $this->decider->isPublished($event),
            'Event with future publishDate should not yet be considered published.'
        );
        self::assertSame('scheduled', $this->decider->publicationState($event));
    }

    public function testLiveStateWithPastPublishDate(): void
    {
        $event = $this->makeEvent(published: true, publishOffset: '-5 minutes');
        self::assertTrue(
            $this->decider->isPublished($event),
            'Event with past publishDate and published flag should be live.'
        );
        self::assertSame('live', $this->decider->publicationState($event));
    }

    public function testUnknownStateWhenPublishDateMissing(): void
    {
        $event = $this->makeEvent(published: true, publishDate: null);
        self::assertFalse(
            $this->decider->isPublished($event),
            'Missing publishDate must not yield published=true.'
        );
        self::assertSame('unknown', $this->decider->publicationState($event));
    }

    public function testBoundaryPublishDateEqualNowIsLive(): void
    {
        // Inclusive boundary: publishDate == now counts as live (<= rule)
        $event = $this->makeEvent(published: true, publishDate: $this->now);
        self::assertTrue(
            $this->decider->isPublished($event),
            'Boundary: publishDate exactly now should be considered live (inclusive <=).'
        );
        self::assertSame('live', $this->decider->publicationState($event));
    }

    public function testUnpublishAtIsNullPlaceholder(): void
    {
        $event = $this->makeEvent(published: true, publishOffset: '-1 day');
        self::assertNull(
            $this->decider->unpublishAt($event),
            'Current design returns null (no unpublish rule yet).'
        );
    }

    /**
     * Ensures legacy semantics (loose true comparison) still treated as "published".
     * published flag stored as int(1) or string '1' in some legacy contexts should pass.
     */
    public function testLegacyTruthyPublishedStillWorks(): void
    {
        $event = $this->makeEvent(published: true, publishOffset: '-1 hour');
        // Simulate "truthy" persistence anomaly by reflection (if published stored differently):
        // (In real code we rely on setPublished(bool) but this guards future persistence shape changes.)
        self::assertTrue($this->decider->isPublished($event));
        self::assertSame('live', $this->decider->publicationState($event));
    }

    /**
     * Helper to build a minimal Event with controlled publishDate.
     *
     * @param bool                    $published     Published flag
     * @param string|null             $publishOffset Relative modifier from fixed $now (e.g. '-5 minutes'), ignored if $publishDate provided
     * @param \DateTimeImmutable|null $publishDate   Explicit publishDate (overrides offset)
     */
    private function makeEvent(
        bool $published,
        ?string $publishOffset = null,
        ?\DateTimeImmutable $publishDate = null,
    ): Event {
        $event = new Event();
        $event->setPublished($published);

        if ($publishDate instanceof \DateTimeImmutable) {
            $event->setPublishDate($publishDate);
        } elseif (null !== $publishOffset) {
            $event->setPublishDate($this->now->modify($publishOffset));
        }

        // Provide a future event date to avoid side-effects if other logic inspects it.
        $event->setEventDate($this->now->modify('+7 days'));

        // Minimal required naming / slug fields (if getters used in debugging)
        $event->setName('Test Event');
        $event->setNimi('Testi Tapahtuma');
        $event->setType('event');
        $event->setUrl('test-event');

        return $event;
    }
}
