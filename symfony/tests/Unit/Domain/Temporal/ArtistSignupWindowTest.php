<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Temporal;

use App\Domain\Temporal\ArtistSignupWindow;
use App\Entity\Member;
use App\Time\FixedClock;
use PHPUnit\Framework\TestCase;

final class ArtistSignupWindowTest extends TestCase
{
    private \DateTimeImmutable $now;
    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2025-03-01T12:00:00+00:00');
        $this->clock = new FixedClock($this->now);
    }

    public function testWindowOpenWithinInterval(): void
    {
        $window = $this->window(
            enabled: true,
            start: $this->now->modify('-1 day'),
            end: $this->now->modify('+2 days'),
        );

        self::assertTrue($window->isOpen($this->clock));
    }

    public function testWindowClosedWhenDisabled(): void
    {
        $window = $this->window(enabled: false);

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testWindowClosedIfIntervalIncomplete(): void
    {
        $window = new ArtistSignupWindow(
            true,
            null,
            $this->now->modify('+10 minutes'),
            false,
            null,
            null,
        );

        self::assertFalse(
            $window->isOpen($this->clock),
            'Missing start should short-circuit to false.',
        );
    }

    public function testWindowClosedWhenAfterEnd(): void
    {
        $window = $this->window(
            enabled: true,
            start: $this->now->modify('-5 days'),
            end: $this->now->modify('-1 day'),
        );

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testMemberAccessDeniedWhenMembersOnlyAndAnonymous(): void
    {
        $window = $this->window(membersOnly: true);

        self::assertFalse($window->canMemberAccess(null));
        self::assertTrue(
            $window->canMemberAccess(new Member()),
            'Authenticated members should pass the check.',
        );
    }

    public function testInfoLocalized(): void
    {
        $window = $this->window(
            infoFi: 'Tiedote FI',
            infoEn: 'Notice EN',
        );

        self::assertSame('Tiedote FI', $window->getInfoByLocale('fi'));
        self::assertSame('Notice EN', $window->getInfoByLocale('en'));
        self::assertSame(
            'Tiedote FI',
            $window->getInfoByLocale('sv'),
            'Unknown locale should fall back to Finnish copy.',
        );
    }

    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        $window = $this->window(enabled: true);

        self::assertTrue($window->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $window = $this->window(enabled: false);

        self::assertFalse($window->isEnabled());
    }

    public function testRequiresAuthenticationReturnsTrueWhenMembersOnly(): void
    {
        $window = $this->window(membersOnly: true);

        self::assertTrue($window->requiresAuthentication());
    }

    public function testRequiresAuthenticationReturnsFalseWhenNotMembersOnly(): void
    {
        $window = $this->window(membersOnly: false);

        self::assertFalse($window->requiresAuthentication());
    }

    public function testCanMemberAccessReturnsFalseWhenDisabled(): void
    {
        $window = $this->window(enabled: false, membersOnly: false);

        self::assertFalse($window->canMemberAccess(null));
        self::assertFalse($window->canMemberAccess(new Member()));
    }

    public function testCanMemberAccessReturnsTrueWhenNotMembersOnly(): void
    {
        $window = $this->window(enabled: true, membersOnly: false);

        self::assertTrue($window->canMemberAccess(null));
        self::assertTrue($window->canMemberAccess(new Member()));
    }

    public function testWindowClosedWhenEndIsNull(): void
    {
        $window = new ArtistSignupWindow(
            true,
            $this->now->modify('-1 day'),
            null,
            false,
            null,
            null,
        );

        self::assertFalse(
            $window->isOpen($this->clock),
            'Missing end should short-circuit to false.',
        );
    }

    public function testWindowClosedBeforeStart(): void
    {
        $window = $this->window(
            enabled: true,
            start: $this->now->modify('+1 hour'),
            end: $this->now->modify('+2 hours'),
        );

        self::assertFalse($window->isOpen($this->clock));
    }

    private function window(
        bool $enabled = true,
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        bool $membersOnly = false,
        ?string $infoFi = null,
        ?string $infoEn = null,
    ): ArtistSignupWindow {
        return new ArtistSignupWindow(
            $enabled,
            $start ?? $this->now->modify('-1 day'),
            $end ?? $this->now->modify('+1 day'),
            $membersOnly,
            $infoFi,
            $infoEn,
        );
    }
}
