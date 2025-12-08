<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Artist;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\StreamArtist
 */
final class StreamArtistEntityTest extends TestCase
{
    public function testPrePersistSetsStartedAt(): void
    {
        $entity = new StreamArtist();
        // startedAt is uninitialized before prePersist (cannot access it)

        $entity->prePersist();
        $startedAt = $entity->getStartedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $startedAt);
        $now = new \DateTimeImmutable();
        $this->assertLessThanOrEqual(
            2,
            abs($now->getTimestamp() - $startedAt->getTimestamp()),
            'startedAt should be set to now',
        );
    }

    public function testSetAndGetArtist(): void
    {
        $entity = new StreamArtist();
        $artist = $this->createStub(Artist::class);

        $entity->setArtist($artist);
        $this->assertSame($artist, $entity->getArtist());
    }

    public function testSetAndGetStream(): void
    {
        $entity = new StreamArtist();
        $stream = $this->createStub(Stream::class);

        $entity->setStream($stream);
        $this->assertSame($stream, $entity->getStream());
    }

    public function testSetAndGetStartedAt(): void
    {
        $entity = new StreamArtist();
        $dt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $entity->setStartedAt($dt);
        $this->assertSame($dt, $entity->getStartedAt());
    }

    public function testSetAndGetStoppedAt(): void
    {
        $entity = new StreamArtist();
        $dt = new \DateTimeImmutable('2025-01-01 13:00:00');

        $entity->setStoppedAt($dt);
        $this->assertSame($dt, $entity->getStoppedAt());

        $entity->setStoppedAt(null);
        $this->assertNull($entity->getStoppedAt());
    }

    public function testToStringReturnsArtistName(): void
    {
        $entity = new StreamArtist();
        $artist = $this->createStub(Artist::class);
        $artist->method('getName')->willReturn('Test Artist');
        $entity->setArtist($artist);

        $this->assertSame('Test Artist', (string) $entity);
    }

    public function testEdgeCaseSetters(): void
    {
        $entity = new StreamArtist();

        // Only stoppedAt is nullable; artist, stream, startedAt are required
        $entity->setStoppedAt(null);

        $this->assertNull($entity->getStoppedAt());
    }
}
