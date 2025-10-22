<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Factory\StreamArtistFactory;
use App\Factory\StreamFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;

final class StreamWorkflowTest extends FixturesWebTestCase
{
    use Factories;
    use LoginHelperTrait;

    #[Test]
    public function userCanSeeStreamDetailsInArtistProfile(): void
    {
        // Given: A member with an artist
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()
            ->withMember($member)
            ->band()
            ->create();

        // And: An online stream
        $stream = StreamFactory::new()->online()->create();

        // And: The artist has a past stream session (already stopped)
        $pastStreamArtist = StreamArtistFactory::new()
            ->forArtist($artist)
            ->inStream($stream)
            ->stopped()
            ->create();

        // And: User is logged in
        $this->loginAsMember($member->getEmail());

        // When: User visits their artist's streams page
        $artistId = $artist->getId();
        $this->client->request('GET', "/profiili/artisti/{$artistId}/streamit");

        // Then: The page should load successfully
        $this->assertResponseIsSuccessful();

        // And: The page should show the artist's streams
        $this->client->assertSelectorTextContains('h1', $artist->getName());
    }

    #[Test]
    public function userCannotAccessOtherMembersArtistStreams(): void
    {
        // Given: Two members with artists
        $member1 = MemberFactory::new()->active()->create();
        $artist1 = ArtistFactory::new()
            ->withMember($member1)
            ->create();

        $member2 = MemberFactory::new()->active()->create();
        $artist2 = ArtistFactory::new()
            ->withMember($member2)
            ->create();

        // And: User 1 is logged in
        $this->loginAsMember($member1->getEmail());
        $this->seedClientHome('fi');

        // When: User 1 tries to access User 2's artist streams
        $artist2Id = $artist2->getId();
        $this->client->request('GET', "/profiili/artisti/{$artist2Id}/streamit");

        // Then: User should be redirected (access denied)
        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode(), 'Should redirect when accessing other member\'s artist');
        $this->assertStringContainsString('/profiili/artisti', $response->headers->get('Location') ?? '');

        // And: Following the redirect should show the artist list
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function multipleArtistsCanBeInStreamSimultaneously(): void
    {
        // Given: Two members with artists
        $member1 = MemberFactory::new()->active()->create();
        $artist1 = ArtistFactory::new()
            ->withMember($member1)
            ->dj()
            ->create();

        $member2 = MemberFactory::new()->active()->create();
        $artist2 = ArtistFactory::new()
            ->withMember($member2)
            ->band()
            ->create();

        // And: An online stream
        $stream = StreamFactory::new()->online()->create();

        // When: Both artists are added to the stream
        $streamArtist1 = StreamArtistFactory::new()
            ->forArtist($artist1)
            ->inStream($stream)
            ->active()
            ->create();

        $streamArtist2 = StreamArtistFactory::new()
            ->forArtist($artist2)
            ->inStream($stream)
            ->active()
            ->create();

        // Then: Both stream artists should be active
        $this->assertNull($streamArtist1->getStoppedAt());
        $this->assertNull($streamArtist2->getStoppedAt());

        // And: Both should be in the same stream
        $this->assertSame($stream->getId(), $streamArtist1->getStream()->getId());
        $this->assertSame($stream->getId(), $streamArtist2->getStream()->getId());
    }

    #[Test]
    public function streamPageAccessibleWhenNoStreamIsOnline(): void
    {
        // Given: A member with an artist
        $member = MemberFactory::new()->active()->create();
        ArtistFactory::new()
            ->withMember($member)
            ->create();

        // And: No online stream exists
        // (default state - no streams created)

        // And: User is logged in
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        // When: User visits the stream page
        $this->client->request('GET', '/stream');

        // Then: The page should still load successfully
        $this->assertResponseIsSuccessful();

        // And: The artist control component should be present
        // (but may show different UI state)
        $this->client->assertSelectorExists('[data-live-name-value="Stream:ArtistControl"]');
    }

    #[Test]
    public function englishStreamRoutesWork(): void
    {
        // Given: A member with an artist
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()
            ->withMember($member)
            ->create();

        // And: User is logged in
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        // When: User visits the English stream page
        $this->client->request('GET', '/en/stream');

        // Then: The page should load successfully
        $this->assertResponseIsSuccessful();

        // When: User visits artist streams in English
        $artistId = $artist->getId();
        $this->client->request('GET', "/en/profile/artist/{$artistId}/streams");

        // Then: The page should load successfully
        $this->assertResponseIsSuccessful();

        // And: The page should show the artist's streams
        $this->client->assertSelectorTextContains('h1', $artist->getName());
    }
}
