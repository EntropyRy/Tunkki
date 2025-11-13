<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Entity\Member;
use App\Time\MutableClock;
use PHPUnit\Framework\TestCase;

final class EventTemporalStateServicePhaseTest extends TestCase
{
    private MutableClock $clock;
    private EventTemporalStateService $service;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2025-08-15T15:00:00+00:00');
        $this->clock = new MutableClock($this->now);
        $this->service = new EventTemporalStateService($this->clock);
    }

    public function testPhaseBeforeSingleDayEvent(): void
    {
        $event = $this->baseEvent();
        $event->setEventDate($this->now->modify('+2 days'));

        self::assertSame('before', $this->service->getPhase($event));
        self::assertFalse($this->service->isInPast($event));
    }

    public function testPhaseDuringMultidayWindow(): void
    {
        $event = $this->baseEvent();
        $event->setEventDate($this->now->modify('-1 day'));
        $event->setUntil($this->now->modify('+1 day'));

        self::assertSame('now', $this->service->getPhase($event));
        self::assertFalse($this->service->isInPast($event));
    }

    public function testPhaseAfterSingleDayWhenNoUntil(): void
    {
        $event = $this->baseEvent();
        $event->setEventDate($this->now->modify('-10 minutes'));

        self::assertSame('after', $this->service->getPhase($event));
        self::assertTrue($this->service->isInPast($event));
    }

    public function testSignupOpenOnlyWhenWindowAndEventNotPast(): void
    {
        $event = $this->baseEvent();
        $event->setEventDate($this->now->modify('+5 days'));
        $event->setArtistSignUpEnabled(true);
        $event->setArtistSignUpStart($this->now->modify('-2 days'));
        $event->setArtistSignUpEnd($this->now->modify('+2 days'));

        self::assertTrue($this->service->isSignupOpen($event));

        $event->setEventDate($this->now->modify('-1 day'));
        self::assertFalse(
            $this->service->isSignupOpen($event),
            'Past events should force signup closed even if window matches.',
        );
    }

    public function testCanShowSignupLinkRequiresMemberWhenConfigured(): void
    {
        $event = $this->baseEvent();
        $event->setEventDate($this->now->modify('+1 day'));
        $event->setArtistSignUpEnabled(true);
        $event->setArtistSignUpStart($this->now->modify('-1 day'));
        $event->setArtistSignUpEnd($this->now->modify('+1 day'));
        $event->setShowArtistSignUpOnlyForLoggedInMembers(true);

        self::assertFalse($this->service->canShowSignupLink($event, null));
        self::assertTrue(
            $this->service->canShowSignupLink($event, new Member()),
        );
    }

    public function testPresaleOpenWithinWindow(): void
    {
        $event = $this->baseEvent();
        $event->setTicketsEnabled(true);
        $event->setTicketPresaleStart($this->now->modify('-1 day'));
        $event->setTicketPresaleEnd($this->now->modify('+3 days'));

        self::assertTrue($this->service->isPresaleOpen($event));

        $event->setTicketPresaleStart($this->now->modify('+1 day'));
        self::assertFalse($this->service->isPresaleOpen($event));
    }

    public function testBadgeKeyForAnnouncement(): void
    {
        $event = $this->baseEvent();
        $event->setType('announcement');

        self::assertSame('Announcement', $this->service->badgeKey($event));
    }

    private function baseEvent(): Event
    {
        $event = new Event();
        $event->setEventDate($this->now->modify('+1 day'));
        $event->setPublished(true);
        $event->setPublishDate($this->now->modify('-1 hour'));
        $event->setTicketsEnabled(false);
        $event->setArtistSignUpEnabled(false);
        $event->setType('event');

        return $event;
    }
}
