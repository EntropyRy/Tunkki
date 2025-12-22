<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Stream;

use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Stream\Player;

final class PlayerComponentTest extends LiveComponentTestCase
{
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

    public function testSetStreamFormatIgnoresInvalidValues(): void
    {
        $component = $this->mountComponent(Player::class);
        $component->render();

        $component->call('setStreamFormat', ['format' => 'flac']);
        $this->assertComponentNotDispatchBrowserEvent($component, 'stream:format-changed');

        /** @var Player $player */
        $player = $component->component();
        self::assertSame(Player::FORMAT_MP3, $player->streamFormat);
    }
}
