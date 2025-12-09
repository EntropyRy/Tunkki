<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Temporal\ArtistSignupWindow;
use App\Domain\Temporal\TicketPresaleWindow;
use App\Entity\Event;
use App\Entity\Member;
use App\Time\ClockInterface;

/**
 * Centralized temporal decision service for Events.
 *
 * Replaces the legacy EventPublicationDecider + ad-hoc entity helpers by
 * routing every "now" calculation through ClockInterface-driven value objects.
 */
final readonly class EventTemporalStateService
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function isPublished(Event $event): bool
    {
        $publishDate = $event->getPublishDate();
        if (!$publishDate instanceof \DateTimeInterface) {
            return false;
        }

        if (true != $event->getPublished()) {
            // intentionally loose comparison retained from legacy semantics
            return false;
        }

        return $publishDate <= $this->clock->now();
    }

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

        return $publishDate <= $this->clock->now() ? 'live' : 'scheduled';
    }

    public function unpublishAt(): null
    {
        return null;
    }

    /**
     * Mirrors the legacy getNowTest() semantic: before | now | after | undefined.
     */
    public function getPhase(Event $event): string
    {
        $now = $this->clock->now();
        $eventDate = $event->getEventDate();
        $until = $event->getExplicitUntil();

        $nowS = (int) $now->format('U');
        $eventS = (int) $eventDate->format('U');

        if ($until instanceof \DateTimeInterface) {
            $untilS = (int) $until->format('U');
            $tolerance = 1;

            if ($nowS >= $eventS && $nowS <= $untilS + $tolerance) {
                return 'now';
            }

            if ($nowS > $untilS + $tolerance) {
                return 'after';
            }

            // Only remaining case: nowS < eventS
            return 'before';
        }

        if ($nowS < $eventS) {
            return 'before';
        }

        // Without an "until" marker the event is treated as past immediately after start.
        return 'after';
    }

    public function isInPast(Event $event): bool
    {
        return 'after' === $this->getPhase($event);
    }

    public function badgeKey(Event $event): string
    {
        if ('announcement' === $event->getType()) {
            return 'Announcement';
        }

        return match ($this->getPhase($event)) {
            'now' => 'event.now',
            'after' => 'event.after',
            default => 'event.in_future',
        };
    }

    public function isSignupOpen(Event $event): bool
    {
        $window = $this->artistSignupWindow($event);

        if ($this->isInPast($event)) {
            return false;
        }

        return $window->isOpen($this->clock);
    }

    public function artistSignupInfo(Event $event, string $locale): ?string
    {
        return $this->artistSignupWindow($event)->getInfoByLocale($locale);
    }

    public function canShowSignupLink(Event $event, ?Member $member): bool
    {
        $window = $this->artistSignupWindow($event);

        if (!$window->isOpen($this->clock)) {
            return false;
        }

        if ($this->isInPast($event)) {
            return false;
        }

        return $window->canMemberAccess($member);
    }

    public function isPresaleOpen(Event $event): bool
    {
        return $this->ticketPresaleWindow($event)->isOpen($this->clock);
    }

    public function ticketInfo(Event $event, string $locale): ?string
    {
        return $this->ticketPresaleWindow($event)->getInfoByLocale($locale);
    }

    private function artistSignupWindow(Event $event): ArtistSignupWindow
    {
        return new ArtistSignupWindow(
            (bool) $event->getArtistSignUpEnabled(),
            $event->getArtistSignUpStart(),
            $event->getArtistSignUpEnd(),
            $event->isShowArtistSignUpOnlyForLoggedInMembers(),
            $event->getArtistSignUpInfoFi(),
            $event->getArtistSignUpInfoEn(),
        );
    }

    private function ticketPresaleWindow(Event $event): TicketPresaleWindow
    {
        return new TicketPresaleWindow(
            (bool) $event->getTicketsEnabled(),
            $event->getTicketPresaleStart(),
            $event->getTicketPresaleEnd(),
            $event->getTicketInfoFi(),
            $event->getTicketInfoEn(),
        );
    }
}
