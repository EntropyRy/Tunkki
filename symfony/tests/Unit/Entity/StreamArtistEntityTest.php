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
        $this->assertNull(
            $entity->getStartedAt(),
            'startedAt should be null before prePersist',
        );

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
        $artist = $this->createMock(Artist::class);

        $entity->setArtist($artist);
        $this->assertSame($artist, $entity->getArtist());

        $entity->setArtist(null);
        $this->assertNull($entity->getArtist());
    }

    public function testSetAndGetStream(): void
    {
        $entity = new StreamArtist();
        $stream = $this->createMock(Stream::class);

        $entity->setStream($stream);
        $this->assertSame($stream, $entity->getStream());

        $entity->setStream(null);
        $this->assertNull($entity->getStream());
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
        $artist = $this->createMock(Artist::class);
        $artist->method('getName')->willReturn('TestArtist');

        $entity->setArtist($artist);
        $this->assertSame('TestArtist', (string) $entity);
    }

    public function testToStringWithNullArtist(): void
    {
        $entity = new StreamArtist();
        // __toString will throw if artist is null; test for error
        $this->expectException(\Error::class);
        (string) $entity;
    }

    public function testEdgeCaseSetters(): void
    {
        $entity = new StreamArtist();

        // Setting all associations and fields to null
        $entity->setArtist(null);
        $entity->setStream(null);
        // Do not set startedAt to null, as the setter requires DateTimeImmutable
        // $entity->setStartedAt(null);
        $entity->setStoppedAt(null);

        $this->assertNull($entity->getArtist());
        $this->assertNull($entity->getStream());
        $this->assertNull($entity->getStartedAt());
        $this->assertNull($entity->getStoppedAt());
    }
}
