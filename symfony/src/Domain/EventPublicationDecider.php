<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Event;
use App\Time\ClockInterface;

/**
 * EventPublicationDecider.
 *
 * Domain service responsible for answering publication‑related
 * questions about an Event using a controlled time source.
 *
 * Why this exists instead of putting logic directly on Event:
 *  - Improves testability: time can be frozen via ClockInterface
 *    so boundary conditions (publishDate == now, just before/after)
 *    are deterministic (essential for mutation testing).
 *  - Encourages moving business rules out of the ORM entity to
 *    reduce persistence coupling and ease future refactors.
 *  - Central location to extend publication logic (blackout windows,
 *    embargo rules, locale visibility) without inflating the entity.
 *
 * Typical usage in application/service layer:
 *
 *   $isVisible = $publicationDecider->isPublished($event);
 *   if (!$isVisible) {
 *       // decide whether to 404 / show draft banner / restrict listing, etc.
 *   }
 *
 * Mutation Testing Leverage:
 *  - In tests you can inject a FixedClock and explicitly
 *    set publishDate / eventDate relative to now() to kill
 *    conditional mutants (e.g. '>=' flipped to '>' or '<=').
 *
 * Extension Ideas:
 *  - Add method isPublishWindowOpen() if future introduction of
 *    publishEnd (unpublish) timestamp occurs.
 *  - Introduce a PublicationStatus enum to return richer state
 *    (e.g. DRAFT, SCHEDULED, LIVE, EXPIRED) instead of plain bool.
 */
final readonly class EventPublicationDecider
{
    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * Returns true if the event is considered publicly published
     * at the current clock instant.
     *
     * Rules (minimal initial set):
     *  1. Event must have the published flag truthy (== true)
     *  2. publishDate must be non-null
     *  3. publishDate must be earlier than or equal to "now" (inclusive boundary)
     *
     * Inclusive (<=) boundary:
     *  - Ensures immediate visibility at the exact stored timestamp
     *  - Eliminates a zero‑width “pending” window
     *  - Aligns boundary-focused mutation/edge tests with business expectations
     */
    public function isPublished(Event $event): bool
    {
        $publishDate = $event->getPublishDate();
        if (!$publishDate instanceof \DateTimeInterface) {
            return false;
        }

        if (true != $event->getPublished()) { // intentionally loose compare retained from legacy semantics
            return false;
        }

        $now = $this->clock->now();

        // Boundary rule adjusted from strict `<` to `<=` so that an event with publishDate exactly equal
        // to the current clock instant is considered live (aligns with boundary tests expecting inclusion).
        return $publishDate <= $now;
    }

    /**
     * Returns a more descriptive publication state useful for UI / logging.
     *
     * States:
     *  - 'draft'      : not marked published
     *  - 'scheduled'  : published flag true, publishDate in future
     *  - 'live'       : published flag true, publishDate in past
     *  - 'unknown'    : publishDate missing while published flag true (data anomaly)
     */
    public function publicationState(Event $event): string
    {
        $flag = true == $event->getPublished();
        $publishDate = $event->getPublishDate();

        if (!$flag) {
            return 'draft';
        }

        if (!$publishDate instanceof \DateTimeInterface) {
            return 'unknown';
        }

        $now = $this->clock->now();

        if ($publishDate <= $now) {
            return 'live';
        }

        return 'scheduled';
    }

    /**
     * Future placeholder: Consider eventDate interplay.
     * For example, if an event auto-unpublishes after eventDate + grace period.
     * Keeping a hook method aids mutation test planning (you can assert current
     * design explicitly returns null rather than silently guessing).
     */
    public function unpublishAt(): ?\DateTimeImmutable
    {
        // Currently no unpublish rule; return null explicitly.
        return null;
    }
}
