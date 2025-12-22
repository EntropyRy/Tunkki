<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components\Stream;

use App\Entity\Stream;
use App\Repository\StreamRepository;
use App\Twig\Components\Stream\Player;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\UX\LiveComponent\LiveResponder;

final class PlayerTest extends TestCase
{
    public function testCheckStreamStatusSetsOfflineStateWhenStreamMissing(): void
    {
        $repository = $this->createStub(StreamRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $httpClient = $this->createStub(HttpClientInterface::class);

        $player = $this->createPlayer($httpClient, $repository);
        $player->isOnline = true;

        $player->checkStreamStatus();

        self::assertFalse($player->isOnline);
        self::assertSame($player->offlineImg, $player->currentImg);
        self::assertSame('OFFLINE', $player->badgeText);
        self::assertFalse($player->showPlayer);
        self::assertSame(0, $player->listeners);
        self::assertSame(20000, $player->refreshInterval);
    }

    public function testCheckStreamStatusSetsOnlineStateAndUpdatesListeners(): void
    {
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setListeners(2);

        $repository = $this->createStub(StreamRepository::class);
        $repository->method('findOneBy')->willReturn($stream);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('<div class="mount"><listeners>5</listeners></div>');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $player = $this->createPlayer($httpClient, $repository);
        $player->checkStreamStatus();

        self::assertTrue($player->isOnline);
        self::assertSame($player->onlineImg, $player->currentImg);
        self::assertSame(10000, $player->refreshInterval);
        self::assertSame(5, $player->listeners);
        self::assertSame('ONLINE: 5', $player->badgeText);
        self::assertTrue($player->showPlayer);
        self::assertSame(5, $stream->getListeners());
    }

    public function testCheckStreamStatusSwallowsHttpClientErrors(): void
    {
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setListeners(1);

        $repository = $this->createStub(StreamRepository::class);
        $repository->method('findOneBy')->willReturn($stream);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('boom'));

        $player = $this->createPlayer($httpClient, $repository);
        $player->checkStreamStatus();

        self::assertTrue($player->isOnline);
        self::assertSame($player->onlineImg, $player->currentImg);
        self::assertSame(10000, $player->refreshInterval);
    }

    public function testSetOnlineStateDoesNothingWhenAlreadyOnline(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $repository = $this->createStub(StreamRepository::class);
        $player = $this->createPlayer($httpClient, $repository);
        $player->isOnline = true;
        $player->refreshInterval = 7777;
        $player->currentImg = 'custom-image';

        $responder = $this->getLiveResponder($player);

        $this->callPrivateMethod($player, 'setOnlineState');

        self::assertSame(7777, $player->refreshInterval);
        self::assertSame('custom-image', $player->currentImg);
        self::assertSame([], $responder->getEventsToEmit());
    }

    public function testUpdateStreamListenersDoesNothingWithoutStream(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $repository = $this->createStub(StreamRepository::class);
        $player = $this->createPlayer($httpClient, $repository);
        $player->stream = null;

        $responder = $this->getLiveResponder($player);

        $this->callPrivateMethod($player, 'updateStreamListeners', [5]);

        self::assertSame([], $responder->getEventsToEmit());
    }

    public function testGetStreamMimeTypeMatchesFormat(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $repository = $this->createStub(StreamRepository::class);
        $player = $this->createPlayer($httpClient, $repository);

        $player->streamFormat = Player::FORMAT_MP3;
        self::assertSame('audio/mpeg', $player->getStreamMimeType());

        $player->streamFormat = Player::FORMAT_OPUS;
        self::assertSame('application/ogg', $player->getStreamMimeType());
    }

    private function createPlayer(
        HttpClientInterface $httpClient,
        StreamRepository $repository,
    ): Player {
        $player = new Player($httpClient, $repository);
        $player->setLiveResponder(new LiveResponder());

        return $player;
    }

    private function getLiveResponder(Player $player): LiveResponder
    {
        $reflection = new \ReflectionObject($player);
        $property = $reflection->getProperty('liveResponder');
        $property->setAccessible(true);

        return $property->getValue($player);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function callPrivateMethod(object $target, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionObject($target);
        $refMethod = $reflection->getMethod($method);
        $refMethod->setAccessible(true);

        return $refMethod->invokeArgs($target, $arguments);
    }
}
