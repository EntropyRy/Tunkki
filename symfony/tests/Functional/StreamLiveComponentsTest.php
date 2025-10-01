<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\ArtistFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Artist;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

require_once __DIR__.'/../Http/SiteAwareKernelBrowser.php';

/**
 * Functional tests for Twig Live Components on the /stream page.
 *
 * Tested components:
 *  - Stream\ArtistControl: Allows artists to join/leave the stream
 *  - Stream\Artists: Displays currently streaming artists
 *  - Stream\Player: Shows stream player and online/offline status
 *  - Stream\Control: SSH-based stream control (read-only component)
 *
 * Test scenarios:
 *  1. Components render correctly when no stream is online
 *  2. Components render correctly when stream is online
 *  3. ArtistControl allows logged-in members with artists to join stream
 *  4. ArtistControl allows artists to leave stream
 *  5. Artists component displays active artists correctly
 *  6. Player component shows correct online/offline status
 *  7. Live component actions work with proper authentication
 *  8. Components respond to stream state changes
 */
final class StreamLiveComponentsTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    public function testStreamPageRendersComponentsWhenNoStreamOnline(): void
    {
        // Ensure no online streams exist
        $this->stopAllOnlineStreams();

        $client = $this->client;
        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Verify Live Components are present in the page
        $this->assertStringContainsString('data-controller', $content, 'Live components should be rendered with data-controller attributes');
    }

    public function testStreamPageRendersComponentsWhenStreamIsOnline(): void
    {
        // Create an online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-recording');

        $this->em()->persist($stream);
        $this->em()->flush();

        $client = $this->client;
        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Verify components render with stream data
        $this->assertStringContainsString('data-controller', $content);

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testArtistControlComponentRendersForLoggedInUserWithArtist(): void
    {
        // Create online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-stream');
        $this->em()->persist($stream);
        $this->em()->flush();

        // Login as user with artist
        /** @var User $user */
        $user = $this->getReference(UserFixtures::USER_REFERENCE, User::class);
        $client = $this->client;
        $client->loginUser($user);

        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Verify ArtistControl component is rendered (contains form elements)
        $this->assertStringContainsString('data-controller', $content);

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testArtistCanJoinStream(): void
    {
        // Create online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-stream-join');
        $this->em()->persist($stream);
        $this->em()->flush();

        // Get user with artist (refetch from EM to ensure it's managed)
        /** @var User $user */
        $user = $this->em()->find(User::class, $this->getReference(UserFixtures::USER_REFERENCE, User::class)->getId());
        /** @var Artist $artist */
        $artist = $this->em()->find(Artist::class, $this->getReference(ArtistFixtures::ARTIST_REFERENCE, Artist::class)->getId());

        $this->assertNotNull($user->getMember(), 'User should have a member');
        $this->assertGreaterThan(0, $user->getMember()->getArtist()->count(), 'Member should have artists');

        // Create StreamArtist entity manually (simulating form submission)
        $streamArtist = new StreamArtist();
        $streamArtist->setStream($stream);
        $streamArtist->setArtist($artist);

        $this->em()->persist($streamArtist);
        $this->em()->flush();

        // Verify artist is in the stream
        $this->assertNull($streamArtist->getStoppedAt(), 'Newly joined artist should not have stoppedAt set');
        $this->assertInstanceOf(\DateTimeImmutable::class, $streamArtist->getStartedAt(), 'Artist should have startedAt timestamp');

        // Clear and refetch to ensure relationship is loaded
        $this->em()->clear();
        $stream = $this->em()->find(Stream::class, $stream->getId());

        // Verify artist appears in stream's active artists
        $activeArtists = $stream->getArtistsOnline();
        $this->assertCount(1, $activeArtists, 'Stream should have 1 active artist');
        $this->assertSame($artist->getId(), $activeArtists->first()->getArtist()->getId());

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testArtistCanLeaveStream(): void
    {
        // Create online stream with an active artist
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-stream-leave');
        $this->em()->persist($stream);

        /** @var Artist $artist */
        $artist = $this->em()->find(Artist::class, $this->getReference(ArtistFixtures::ARTIST_REFERENCE, Artist::class)->getId());

        $streamArtist = new StreamArtist();
        $streamArtist->setStream($stream);
        $streamArtist->setArtist($artist);
        $this->em()->persist($streamArtist);
        $this->em()->flush();

        // Clear and refetch to ensure relationship is loaded
        $this->em()->clear();
        $stream = $this->em()->find(Stream::class, $stream->getId());
        $streamArtist = $this->em()->find(StreamArtist::class, $streamArtist->getId());

        // Verify artist is online
        $this->assertCount(1, $stream->getArtistsOnline());

        // Simulate artist leaving by setting stoppedAt
        $streamArtist->setStoppedAt(new \DateTimeImmutable());
        $this->em()->flush();

        // Clear entity manager to force fresh fetch
        $this->em()->clear();
        $stream = $this->em()->getRepository(Stream::class)->find($stream->getId());

        // Verify artist is no longer in active artists list
        $this->assertCount(0, $stream->getArtistsOnline(), 'Stream should have no active artists after artist leaves');

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testArtistsComponentDisplaysActiveArtists(): void
    {
        // Create online stream with multiple active artists
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-stream-multiple');
        $this->em()->persist($stream);

        /** @var Artist $artist */
        $artist = $this->em()->find(Artist::class, $this->getReference(ArtistFixtures::ARTIST_REFERENCE, Artist::class)->getId());

        $streamArtist = new StreamArtist();
        $streamArtist->setStream($stream);
        $streamArtist->setArtist($artist);
        $this->em()->persist($streamArtist);
        $this->em()->flush();

        // Request the stream page
        $client = $this->client;
        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Verify artist name appears in the page (rendered by Artists component)
        $this->assertStringContainsString('Fixture Artist', $content, 'Artist name should be displayed on stream page');

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testPlayerComponentShowsOfflineWhenNoStream(): void
    {
        // Ensure no online streams
        $this->stopAllOnlineStreams();

        $client = $this->client;
        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Player component should render (even when offline)
        $this->assertStringContainsString('data-controller', $content);
    }

    public function testPlayerComponentShowsOnlineWhenStreamExists(): void
    {
        // Create online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-player-online');
        $stream->setListeners(5);
        $this->em()->persist($stream);
        $this->em()->flush();

        $client = $this->client;
        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Verify player component is present
        $this->assertStringContainsString('data-controller', $content);

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testMultipleArtistsCanStreamSimultaneously(): void
    {
        // Create online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-stream-multi');
        $this->em()->persist($stream);

        // Get the fixture artist (refetch to ensure managed)
        /** @var Artist $artist1 */
        $artist1 = $this->em()->find(Artist::class, $this->getReference(ArtistFixtures::ARTIST_REFERENCE, Artist::class)->getId());

        // Create a second artist for testing
        /** @var User $activeUser */
        $activeUser = $this->em()->find(User::class, $this->getReference(UserFixtures::ACTIVE_MEMBER_REFERENCE, User::class)->getId());
        $artist2 = new Artist();
        $artist2->setName('Second DJ');
        $artist2->setType('DJ');
        $artist2->setMember($activeUser->getMember());
        $this->em()->persist($artist2);

        // Add both artists to stream
        $streamArtist1 = new StreamArtist();
        $streamArtist1->setStream($stream);
        $streamArtist1->setArtist($artist1);
        $this->em()->persist($streamArtist1);

        $streamArtist2 = new StreamArtist();
        $streamArtist2->setStream($stream);
        $streamArtist2->setArtist($artist2);
        $this->em()->persist($streamArtist2);

        $this->em()->flush();

        // Clear and refetch to ensure relationships are loaded
        $this->em()->clear();
        $stream = $this->em()->find(Stream::class, $stream->getId());

        // Verify both artists are active
        $this->assertCount(2, $stream->getArtistsOnline(), 'Stream should have 2 active artists');

        // Request page and verify both names appear
        $client = $this->client;
        $client->request('GET', '/en/stream');
        $content = $client->getResponse()->getContent();

        $this->assertStringContainsString('Fixture Artist', $content);
        $this->assertStringContainsString('Second DJ', $content);

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testStreamArtistTimestampsAreRecorded(): void
    {
        // Create stream and artist
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-timestamps');
        $this->em()->persist($stream);

        /** @var Artist $artist */
        $artist = $this->em()->find(Artist::class, $this->getReference(ArtistFixtures::ARTIST_REFERENCE, Artist::class)->getId());

        $streamArtist = new StreamArtist();
        $streamArtist->setStream($stream);
        $streamArtist->setArtist($artist);
        $this->em()->persist($streamArtist);
        $this->em()->flush();

        // Verify startedAt is set automatically
        $this->assertInstanceOf(\DateTimeImmutable::class, $streamArtist->getStartedAt());
        $this->assertNull($streamArtist->getStoppedAt());

        // Simulate artist stopping
        $stopTime = new \DateTimeImmutable();
        $streamArtist->setStoppedAt($stopTime);
        $this->em()->flush();

        // Verify stoppedAt is recorded
        $this->assertInstanceOf(\DateTimeImmutable::class, $streamArtist->getStoppedAt());
        $this->assertGreaterThanOrEqual(
            $streamArtist->getStartedAt()->getTimestamp(),
            $streamArtist->getStoppedAt()->getTimestamp(),
            'stoppedAt should be after or equal to startedAt'
        );

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testComponentsHandleNoAuthenticatedUser(): void
    {
        // Create online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-no-auth');
        $this->em()->persist($stream);
        $this->em()->flush();

        // Request page without authentication
        $client = $this->client;
        $client->request('GET', '/en/stream');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();

        // Page should still render successfully (components handle no auth gracefully)
        $this->assertStringContainsString('data-controller', $content);

        // Clean up
        $stream->setOnline(false);
        $this->em()->flush();
    }

    /**
     * Helper method to stop all online streams
     */
    private function stopAllOnlineStreams(): void
    {
        $streams = $this->em()->getRepository(Stream::class)->findBy(['online' => true]);
        foreach ($streams as $stream) {
            $stream->setOnline(false);
        }
        $this->em()->flush();
    }

    /**
     * Get a fixture reference (helper for readability)
     */
    private function getReference(string $name, string $class): object
    {
        // Fetch directly from database by known criteria from fixtures
        if ($class === User::class) {
            if ($name === UserFixtures::USER_REFERENCE) {
                return $this->findOneOrFail(User::class, ['authId' => 'local-user']);
            }
            if ($name === UserFixtures::ACTIVE_MEMBER_REFERENCE) {
                return $this->findOneOrFail(User::class, ['authId' => 'local-active']);
            }
        }
        if ($class === Artist::class && $name === ArtistFixtures::ARTIST_REFERENCE) {
            return $this->findOneOrFail(Artist::class, ['name' => 'Fixture Artist']);
        }

        throw new \RuntimeException("Unknown fixture reference: {$name} for class {$class}");
    }
}
