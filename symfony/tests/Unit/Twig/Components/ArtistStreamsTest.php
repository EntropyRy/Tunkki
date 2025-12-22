<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components;

use App\Entity\Artist;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use App\Repository\StreamArtistRepository;
use App\Twig\Components\ArtistStreams;
use PHPUnit\Framework\TestCase;

final class ArtistStreamsTest extends TestCase
{
    public function testHasTimeOverlapReturnsTrueWhenEndIsMissing(): void
    {
        $stream = new Stream();
        $artistA = $this->createArtist('Artist A', 1);
        $artistB = $this->createArtist('Artist B', 2);

        $current = $this->createStreamArtist(
            $artistA,
            $stream,
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            null,
        );
        $other = $this->createStreamArtist(
            $artistB,
            $stream,
            new \DateTimeImmutable('2025-01-01 10:30:00'),
            null,
        );

        $component = $this->createComponent();

        $overlap = $this->callPrivateMethod($component, 'hasTimeOverlap', [$current, $other]);

        self::assertTrue($overlap);
    }

    public function testHasTimeOverlapReturnsFalseWhenTimesDoNotOverlap(): void
    {
        $stream = new Stream();
        $artistA = $this->createArtist('Artist A', 1);
        $artistB = $this->createArtist('Artist B', 2);

        $current = $this->createStreamArtist(
            $artistA,
            $stream,
            new \DateTimeImmutable('2025-01-01 08:00:00'),
            new \DateTimeImmutable('2025-01-01 09:00:00'),
        );
        $other = $this->createStreamArtist(
            $artistB,
            $stream,
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            new \DateTimeImmutable('2025-01-01 11:00:00'),
        );

        $component = $this->createComponent();

        $overlap = $this->callPrivateMethod($component, 'hasTimeOverlap', [$current, $other]);

        self::assertFalse($overlap);
    }

    public function testGetOverlappingArtistsForTimeSlotSkipsCurrentArtistAndFiltersOverlap(): void
    {
        $stream = new Stream();
        $artistA = $this->createArtist('Artist A', 1);
        $artistB = $this->createArtist('Artist B', 2);
        $artistC = $this->createArtist('Artist C', 3);

        $current = $this->createStreamArtist(
            $artistA,
            $stream,
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            new \DateTimeImmutable('2025-01-01 11:00:00'),
        );
        $sameArtist = $this->createStreamArtist(
            $artistA,
            $stream,
            new \DateTimeImmutable('2025-01-01 10:30:00'),
            new \DateTimeImmutable('2025-01-01 11:30:00'),
        );
        $overlap = $this->createStreamArtist(
            $artistB,
            $stream,
            new \DateTimeImmutable('2025-01-01 10:30:00'),
            new \DateTimeImmutable('2025-01-01 11:30:00'),
        );
        $noOverlap = $this->createStreamArtist(
            $artistC,
            $stream,
            new \DateTimeImmutable('2025-01-01 12:00:00'),
            new \DateTimeImmutable('2025-01-01 13:00:00'),
        );

        $repository = $this->createStub(StreamArtistRepository::class);
        $repository->method('findBy')->willReturn([
            $current,
            $sameArtist,
            $overlap,
            $noOverlap,
        ]);

        $component = $this->createComponent($repository);

        $result = $this->callPrivateMethod(
            $component,
            'getOverlappingArtistsForTimeSlot',
            [$stream, $artistA, $current],
        );

        self::assertSame(['Artist B'], $result);
    }

    private function createComponent(?StreamArtistRepository $repository = null): ArtistStreams
    {
        $repository ??= $this->createStub(StreamArtistRepository::class);

        return new ArtistStreams($repository);
    }

    private function createArtist(string $name, int $id): Artist
    {
        $artist = new Artist();
        $artist->setName($name);
        $this->setEntityId($artist, $id);

        return $artist;
    }

    private function createStreamArtist(
        Artist $artist,
        Stream $stream,
        \DateTimeImmutable $startedAt,
        ?\DateTimeImmutable $stoppedAt,
    ): StreamArtist {
        $streamArtist = new StreamArtist();
        $streamArtist->setArtist($artist);
        $streamArtist->setStream($stream);
        $streamArtist->setStartedAt($startedAt);
        $streamArtist->setStoppedAt($stoppedAt);

        return $streamArtist;
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

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
