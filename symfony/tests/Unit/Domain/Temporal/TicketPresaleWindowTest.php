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
