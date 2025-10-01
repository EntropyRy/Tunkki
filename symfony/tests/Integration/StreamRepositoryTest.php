<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Stream;
use App\Entity\StreamArtist;
use App\Repository\StreamRepository;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * Focused integration tests for StreamRepository using existing fixture data.
 *
 * Simplified to avoid complex ad‑hoc entity graphs that conflicted with
 * production mappings (e.g. Member.code NOT NULL, cascade expectations).
 *
 * Covered:
 *  - saveAll() with deferred flush
 *  - stopAllOnline() basic behavior (single online stream -> becomes offline)
 *  - stopAllOnline() sets stoppedAt on StreamArtist with previously null value
 *  - filename helper methods
 */
final class StreamRepositoryTest extends FixturesWebTestCase
{
    private StreamRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var StreamRepository $repo */
        $repo = self::$em->getRepository(Stream::class);
        $this->repo = $repo;
    }

    public function testSaveAllWithDeferredFlushPersistsEntities(): void
    {
        $initial = $this->repo->count([]);

        $s1 = new Stream();
        $s1->setOnline(true);
        $s1->setFilename('bulk_stream_one');

        $s2 = new Stream();
        $s2->setOnline(false);
        $s2->setFilename('bulk_stream_two');

        // Deferred flush path
        $this->repo->saveAll([$s1, $s2], false);
        self::assertNull($s1->getId(), 'ID not assigned before manual flush.');
        self::assertNull($s2->getId(), 'ID not assigned before manual flush.');

        self::$em->flush();
        self::$em->clear();

        self::assertSame(
            $initial + 2,
            $this->repo->count([]),
            'Two new Stream rows expected after flush.'
        );
    }

    public function testStopAllOnlineSingleStreamAndArtist(): void
    {
        // Locate existing fixture Artist (created by ArtistFixtures)
        $artist = self::$em->getRepository(\App\Entity\Artist::class)
            ->findOneBy(['name' => 'Fixture Artist']);
        self::assertNotNull($artist, 'Fixture Artist should exist (ArtistFixtures).');

        // Create a single online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('fixture_online_stream');
        $this->repo->save($stream, true);

        // Link a StreamArtist with null stoppedAt
        $sa = new StreamArtist();
        $sa->setArtist($artist);
        $sa->setStream($stream);
        self::$em->persist($sa);
        self::$em->flush();

        self::assertTrue($stream->isOnline(), 'Precondition: stream online.');
        self::assertNull($sa->getStoppedAt(), 'Precondition: stoppedAt initially null.');

        // Execute
        $affected = $this->repo->stopAllOnline();

        self::assertCount(1, $affected, 'Exactly one online stream should have been stopped.');
        self::assertSame($stream->getId(), $affected[0]->getId());

        self::$em->clear();
        /** @var Stream $reloaded */
        $reloaded = $this->repo->find($stream->getId());
        self::assertFalse($reloaded->isOnline(), 'Stream should now be offline.');

        $links = self::$em->getRepository(StreamArtist::class)->findBy(['stream' => $reloaded]);
        self::assertCount(1, $links, 'One StreamArtist link expected.');
        self::assertNotNull($links[0]->getStoppedAt(), 'stoppedAt should be set by stopAllOnline().');

        // Idempotency: second call -> no affected streams
        $second = $this->repo->stopAllOnline();
        self::assertSame([], $second, 'No streams should be returned on second stopAllOnline call.');
    }

    public function testFilenameHelpers(): void
    {
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('session_A');
        $this->repo->save($stream, true);

        self::assertSame('session_A.mp3', $stream->getMp3Filename());
        self::assertSame('session_A.opus', $stream->getOpusFilename());
        self::assertSame('session_A_unprocessed.flac', $stream->getFlacFilename());
    }
}
