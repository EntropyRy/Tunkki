<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Temporal;

use App\Domain\Temporal\TicketPresaleWindow;
use App\Entity\Member;
use App\Time\FixedClock;
use PHPUnit\Framework\TestCase;

final class TicketPresaleWindowTest extends TestCase
{
    private \DateTimeImmutable $now;
    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2025-06-10T09:00:00+00:00');
        $this->clock = new FixedClock($this->now);
    }

    public function testWindowOpenWhenConfigured(): void
    {
        $window = $this->window();

        self::assertTrue($window->isOpen($this->clock));
    }

    public function testWindowClosedWhenDisabled(): void
    {
        $window = $this->window(enabled: false);

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testWindowClosedBeforeStart(): void
    {
        $window = $this->window(
            start: $this->now->modify('+1 hour'),
            end: $this->now->modify('+2 hours'),
        );

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testMemberAccessRespectsMembersOnlyFlag(): void
    {
        $window = $this->window(membersOnly: true);

        self::assertFalse($window->canMemberAccess(null));
        self::assertTrue($window->canMemberAccess(new Member()));
    }

    public function testInfoLocalized(): void
    {
        $window = $this->window(infoFi: 'Myynti FI', infoEn: 'Sale EN');

        self::assertSame('Myynti FI', $window->getInfoByLocale('fi'));
        self::assertSame('Sale EN', $window->getInfoByLocale('en'));
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $window = $this->window(enabled: false);

        self::assertFalse($window->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        $window = $this->window(enabled: true);

        self::assertTrue($window->isEnabled());
    }

    public function testWindowClosedAfterEnd(): void
    {
        $window = $this->window(
            start: $this->now->modify('-3 hours'),
            end: $this->now->modify('-1 hour'),
        );

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testWindowClosedWhenStartIsNull(): void
    {
        $window = new TicketPresaleWindow(
            true,
            null,
            $this->now->modify('+2 hours'),
            null,
            null,
            false,
        );

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testWindowClosedWhenEndIsNull(): void
    {
        $window = new TicketPresaleWindow(
            true,
            $this->now->modify('-2 hours'),
            null,
            null,
            null,
            false,
        );

        self::assertFalse($window->isOpen($this->clock));
    }

    public function testCanMemberAccessReturnsTrueWhenNotMembersOnly(): void
    {
        $window = $this->window(membersOnly: false);

        self::assertTrue($window->canMemberAccess(null));
        self::assertTrue($window->canMemberAccess(new Member()));
    }

    public function testCanMemberAccessReturnsFalseWhenDisabled(): void
    {
        $window = $this->window(enabled: false, membersOnly: false);

        self::assertFalse($window->canMemberAccess(null));
        self::assertFalse($window->canMemberAccess(new Member()));
    }

    private function window(
        bool $enabled = true,
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        ?string $infoFi = null,
        ?string $infoEn = null,
        bool $membersOnly = false,
    ): TicketPresaleWindow {
        return new TicketPresaleWindow(
            $enabled,
            $start ?? $this->now->modify('-2 hours'),
            $end ?? $this->now->modify('+2 hours'),
            $infoFi,
            $infoEn,
            $membersOnly,
        );
    }
}
