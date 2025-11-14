<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Stream;

use App\Factory\StreamFactory;
use App\Repository\StreamRepository;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Stream\Player;

final class PlayerComponentTest extends LiveComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetStreams();
    }

    public function testRendersOfflineStateWhenNoStreamIsOnline(): void
    {
        $component = $this->mountComponent(Player::class);
        $component->render(); // Trigger postMount + polling logic

        /** @var Player $player */
        $player = $component->component();

        self::assertFalse($player->isOnline, 'Component should signal offline state when no stream exists.');
        self::assertSame(
            $player->offlineImg,
            $player->currentImg,
            'Offline state should show offline artwork.',
        );
        self::assertFalse($player->showPlayer, 'Audio controls stay hidden until a stream is online.');
        self::assertSame(0, $player->listeners);
        self::assertSame(Player::FORMAT_MP3, $player->streamFormat, 'Default format should be MP3.');
    }

    public function testRendersOnlineStateWhenStreamExists(): void
    {
        StreamFactory::new()
            ->online()
            ->withListeners(37)
            ->withFilename('kerde-live')
            ->create();

        $component = $this->mountComponent(Player::class);
        $component->render();

        /** @var Player $player */
        $player = $component->component();

        self::assertTrue($player->isOnline);
        self::assertSame('ONLINE: 37', $player->badgeText);
        self::assertTrue($player->showPlayer, 'Player controls should appear once stream is online.');
        self::assertSame(
            $player->onlineImg,
            $player->currentImg,
            'Online state should show online artwork.',
        );
        self::assertSame(10000, $player->refreshInterval, 'Refresh interval switches to fast polling when online.');
    }

    public function testSetStreamFormatDispatchesBrowserEventWhenChanged(): void
    {
        $component = $this->mountComponent(Player::class);
        $component->render();

        $component->call('setStreamFormat', ['format' => Player::FORMAT_OPUS]);
        $this->assertComponentDispatchBrowserEvent(
            $component,
            'stream:format-changed',
        )->withPayloadSubset(['format' => Player::FORMAT_OPUS]);
        self::assertSame(Player::FORMAT_OPUS, $component->component()->streamFormat);
    }

    private function resetStreams(): void
    {
        /** @var StreamRepository $repository */
        $repository = self::getContainer()->get(StreamRepository::class);
        $repository->stopAllOnline();
    }
}
