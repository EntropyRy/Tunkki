<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Stream;

use App\Factory\StreamFactory;
use App\Repository\StreamRepository;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Stream\Artists;

final class ArtistsComponentTest extends LiveComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetStreams();
    }

    public function testMountsOfflineWhenNoStreamIsOnline(): void
    {
        $component = $this->mountComponent(Artists::class);
        $component->render();

        /** @var Artists $artists */
        $artists = $component->component();

        self::assertFalse($artists->isOnline);
        self::assertNull($artists->stream);
    }

    public function testListenerRefreshesStateWhenStreamStatusChanges(): void
    {
        StreamFactory::new()
            ->online()
            ->withListeners(12)
            ->create();

        $component = $this->mountComponent(Artists::class);
        $component->render();

        /** @var Artists $artists */
        $artists = $component->component();
        self::assertTrue($artists->isOnline);
        self::assertNotNull($artists->stream);

        // Stop streams and emit update to verify the component resets its state.
        $this->resetStreams();
        $component->emit('stream:updated');
        $refreshed = $component->component();
        self::assertFalse($refreshed->isOnline);
        self::assertSame('', $refreshed->hash);
        self::assertNull($refreshed->stream);
    }

    public function testListenerUpdatesHashWhenStreamRemainsOnline(): void
    {
        $stream = StreamFactory::new()
            ->online()
            ->withListeners(5)
            ->create();

        $component = $this->mountComponent(Artists::class);
        $component->render();

        $component->emit('stream:updated');

        /** @var Artists $artists */
        $artists = $component->component();
        self::assertTrue($artists->isOnline);
        self::assertSame($stream->getUpdatedAt()->format('U'), $artists->hash);
        self::assertNotNull($artists->stream);
    }

    private function resetStreams(): void
    {
        /** @var StreamRepository $repository */
        $repository = self::getContainer()->get(StreamRepository::class);
        $repository->stopAllOnline();
    }
}
