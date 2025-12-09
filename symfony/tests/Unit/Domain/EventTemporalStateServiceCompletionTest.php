<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Entity\Member;
use App\Time\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * Additional tests to achieve 100% coverage for EventTemporalStateService.
 *
 * Covers methods not tested in EventTemporalStateServicePhaseTest and
 * EventTemporalStateServicePublicationTest.
 */
final class EventTemporalStateServiceCompletionTest extends TestCase
{
    private \DateTimeImmutable $now;
    private FixedClock $clock;
    private EventTemporalStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2025-06-01T12:00:00+00:00');
        $this->clock = new FixedClock($this->now);
        $this->service = new EventTemporalStateService($this->clock);
    }

    public function testIsInPastReturnsTrueForAfterPhase(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-2 days'));

        self::assertTrue($this->service->isInPast($event));
    }

    public function testIsInPastReturnsFalseForNowPhase(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-1 hour'));
        $event->setUntil($this->now->modify('+2 hours'));

        self::assertFalse($this->service->isInPast($event));
    }

    public function testIsInPastReturnsFalseForBeforePhase(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('+3 days'));

        self::assertFalse($this->service->isInPast($event));
    }

    public function testBadgeKeyForNowPhase(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-1 hour'));
        $event->setUntil($this->now->modify('+1 hour'));

        self::assertSame('event.now', $this->service->badgeKey($event));
    }

    public function testBadgeKeyForAfterPhase(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-2 days'));

        self::assertSame('event.after', $this->service->badgeKey($event));
    }

    public function testBadgeKeyForBeforePhase(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('+2 days'));

        self::assertSame('event.in_future', $this->service->badgeKey($event));
    }

    public function testArtistSignupInfoDelegatesToWindow(): void
    {
        $event = $this->event();
        $event->setArtistSignUpInfoFi('Ilmoittautuminen FI');
        $event->setArtistSignUpInfoEn('Signup EN');

        self::assertSame('Ilmoittautuminen FI', $this->service->artistSignupInfo($event, 'fi'));
        self::assertSame('Signup EN', $this->service->artistSignupInfo($event, 'en'));
    }

    public function testTicketInfoDelegatesToWindow(): void
    {
        $event = $this->event();
        $event->setTicketInfoFi('Liput FI');
        $event->setTicketInfoEn('Tickets EN');

        self::assertSame('Liput FI', $this->service->ticketInfo($event, 'fi'));
        self::assertSame('Tickets EN', $this->service->ticketInfo($event, 'en'));
    }

    public function testIsSignupOpenReturnsFalseWhenEventInPast(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-1 week'));
        $event->setArtistSignUpEnabled(true);
        $event->setArtistSignUpStart($this->now->modify('-2 days'));
        $event->setArtistSignUpEnd($this->now->modify('+1 day'));

        self::assertFalse($this->service->isSignupOpen($event));
    }

    public function testIsSignupOpenReturnsTrueWhenWindowOpenAndNotPast(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('+1 week'));
        $event->setArtistSignUpEnabled(true);
        $event->setArtistSignUpStart($this->now->modify('-1 hour'));
        $event->setArtistSignUpEnd($this->now->modify('+2 days'));

        self::assertTrue($this->service->isSignupOpen($event));
    }

    public function testCanShowSignupLinkReturnsFalseWhenWindowClosed(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('+1 week'));
        $event->setArtistSignUpEnabled(true);
        $event->setArtistSignUpStart($this->now->modify('+1 day'));
        $event->setArtistSignUpEnd($this->now->modify('+2 days'));

        self::assertFalse($this->service->canShowSignupLink($event, null));
    }

    public function testCanShowSignupLinkReturnsFalseWhenEventInPast(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-1 day'));
        $event->setArtistSignUpEnabled(true);
        $event->setArtistSignUpStart($this->now->modify('-2 days'));
        $event->setArtistSignUpEnd($this->now->modify('+1 hour'));

        self::assertFalse($this->service->canShowSignupLink($event, null));
    }

    public function testIsPresaleOpenReturnsTrueWithinWindow(): void
    {
        $event = $this->event();
        $event->setTicketsEnabled(true);
        $event->setTicketPresaleStart($this->now->modify('-1 day'));
        $event->setTicketPresaleEnd($this->now->modify('+1 day'));

        self::assertTrue($this->service->isPresaleOpen($event));
    }

    public function testIsPresaleOpenReturnsFalseOutsideWindow(): void
    {
        $event = $this->event();
        $event->setTicketsEnabled(true);
        $event->setTicketPresaleStart($this->now->modify('+1 day'));
        $event->setTicketPresaleEnd($this->now->modify('+2 days'));

        self::assertFalse($this->service->isPresaleOpen($event));
    }

    public function testGetPhaseReturnsAfterWhenPastUntilWithTolerance(): void
    {
        $event = $this->event();
        $event->setEventDate($this->now->modify('-3 days'));
        $event->setUntil($this->now->modify('-2 days'));

        // Clock is now past until + tolerance (1 second)
        // This should hit line 82: return 'after';

        self::assertSame('after', $this->service->getPhase($event));
    }

    public function testGetPhaseReturnsNowAtToleranceBoundary(): void
    {
        // This test attempts to cover the edge case logic around tolerance boundaries
        // Line 89 ('undefined' return) appears logically unreachable with valid inputs,
        // but we test boundary conditions to ensure defensive code paths are verified.

        $event = $this->event();
        $sameTime = $this->now;
        $event->setEventDate($sameTime);
        $event->setUntil($sameTime);

        // Move clock to exactly until + 1 second (at tolerance boundary)
        $clockAfter = new FixedClock($sameTime->modify('+1 second'));
        $serviceAfter = new EventTemporalStateService($clockAfter);

        // At the tolerance boundary, event should still be considered 'now'
        self::assertSame('now', $serviceAfter->getPhase($event));
    }

    private function event(): Event
    {
        $event = new Event();
        $event->setEventDate($this->now->modify('+1 week'));
        $event->setPublished(true);
        $event->setPublishDate($this->now->modify('-1 day'));

        return $event;
    }
}
