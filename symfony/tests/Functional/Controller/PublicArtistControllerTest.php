<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\EventArtistInfo;
use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for PublicArtistController.
 *
 * Tests cover:
 *  - Bilingual routes (/artisti/{id}/{name} and /artist/{id}/{name})
 *  - Artist page rendering with name display
 *  - 404 handling for non-existent artists
 *  - Page title contains artist name
 */
final class PublicArtistControllerTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    #[DataProvider('localeProvider')]
    public function testArtistPageAccessibleInBothLocales(string $locale): void
    {
        $artist = ArtistFactory::new()->dj()->create([
            'name' => 'Test DJ Artist',
            'bio' => 'Finnish bio text',
            'bioEn' => 'English bio text',
        ]);

        $path = 'fi' === $locale
            ? \sprintf('/artisti/%d/%s', $artist->getId(), 'test-dj-artist')
            : \sprintf('/en/artist/%d/%s', $artist->getId(), 'test-dj-artist');

        $this->seedClientHome($locale);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('localeProvider')]
    public function testArtistNameDisplayedOnPage(string $locale): void
    {
        $artistName = 'Unique Artist Name '.uniqid('', true);
        $artist = ArtistFactory::new()->band()->create([
            'name' => $artistName,
            'genre' => 'Electronic',
        ]);

        $path = 'fi' === $locale
            ? \sprintf('/artisti/%d/%s', $artist->getId(), 'unique-artist')
            : \sprintf('/en/artist/%d/%s', $artist->getId(), 'unique-artist');

        $this->seedClientHome($locale);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString($artistName, $content);
    }

    public function testArtistPageTitleContainsArtistName(): void
    {
        $artist = ArtistFactory::new()->create([
            'name' => 'Page Title Artist',
        ]);

        $this->seedClientHome('fi');
        $this->client->request('GET', \sprintf('/artisti/%d/%s', $artist->getId(), 'page-title-artist'));

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorTextContains('title', 'Page Title Artist');
    }

    public function testNonExistentArtistReturns404(): void
    {
        $this->seedClientHome('fi');
        $this->client->request('GET', '/artisti/999999/non-existent');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testNonExistentArtistReturns404English(): void
    {
        $this->seedClientHome('en');
        $this->client->request('GET', '/en/artist/999999/non-existent');

        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('localeProvider')]
    public function testArtistWithBioDisplaysBio(string $locale): void
    {
        $bioPart = 'fi' === $locale ? 'Finnish bio content' : 'English bio content';
        $artist = ArtistFactory::new()->create([
            'name' => 'Bio Test Artist',
            'bio' => 'Finnish bio content here',
            'bioEn' => 'English bio content here',
        ]);

        $path = 'fi' === $locale
            ? \sprintf('/artisti/%d/%s', $artist->getId(), 'bio-test')
            : \sprintf('/en/artist/%d/%s', $artist->getId(), 'bio-test');

        $this->seedClientHome($locale);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString($bioPart, $content);
    }

    public function testArtistWithGenreDisplaysGenre(): void
    {
        $artist = ArtistFactory::new()->create([
            'name' => 'Genre Test Artist',
            'genre' => 'Techno / House',
        ]);

        $this->seedClientHome('fi');
        $this->client->request('GET', \sprintf('/artisti/%d/%s', $artist->getId(), 'genre-test'));

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Techno / House', $content);
    }

    public function testDjTypeArtistPageWorks(): void
    {
        $artist = ArtistFactory::new()->dj()->create([
            'name' => 'DJ Type Test',
        ]);

        $this->seedClientHome('fi');
        $this->client->request('GET', \sprintf('/artisti/%d/%s', $artist->getId(), 'dj-type-test'));

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('html');
    }

    public function testBandTypeArtistPageWorks(): void
    {
        $artist = ArtistFactory::new()->band()->create([
            'name' => 'Band Type Test',
        ]);

        $this->seedClientHome('fi');
        $this->client->request('GET', \sprintf('/artisti/%d/%s', $artist->getId(), 'band-type-test'));

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('html');
    }

    public function testArtistSlugInUrlDoesNotAffectRouting(): void
    {
        $artist = ArtistFactory::new()->create([
            'name' => 'Actual Artist Name',
        ]);

        // The {name} parameter in the URL is decorative - Symfony resolves by {id}
        $this->seedClientHome('fi');
        $this->client->request('GET', \sprintf('/artisti/%d/%s', $artist->getId(), 'completely-different-slug'));

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Actual Artist Name', $content);
    }

    public function testArtistEntityAccessorsAndLinks(): void
    {
        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->create([
            'name' => 'Artist Accessor Test',
            'genre' => 'Electro',
            'type' => 'DJ',
            'bio' => 'Bio FI',
            'bioEn' => 'Bio EN',
            'hardware' => 'Modular',
            'links' => [
                ['url' => 'https://example.test/fractal', 'title' => 'Fractal Instruments'],
                ['url' => 'https://example.test/hexagon', 'title' => 'Hexagon Intergalactic'],
            ],
            'copyForArchive' => true,
        ]);

        self::assertNotNull($artist->getId());
        self::assertSame('Artist Accessor Test', (string) $artist);
        self::assertSame('Electro', $artist->getGenre());
        self::assertSame('dj', $artist->getType());
        self::assertSame('Modular', $artist->getHardware());
        self::assertSame('Bio FI', $artist->getBioByLocale('fi'));
        self::assertSame('Bio EN', $artist->getBioByLocale('en'));
        self::assertSame('Bio EN', $artist->getBioByLocale('sv'));
        $artist->setBio(null);
        self::assertSame('Bio EN', $artist->getBioByLocale('fi'));
        self::assertTrue($artist->getCopyForArchive());
        self::assertInstanceOf(\DateTimeImmutable::class, $artist->getCreatedAt());
        $artist->setUpdatedAt(new \DateTimeImmutable('2030-01-01 12:00:00'));
        self::assertSame('2030-01-01 12:00:00', $artist->getUpdatedAt()->format('Y-m-d H:i:s'));

        $linkHtml = $artist->getLinkUrls();
        self::assertNotNull($linkHtml);
        self::assertStringContainsString('Fractal Instruments', $linkHtml);
        self::assertStringContainsString('Hexagon Intergalactic', $linkHtml);
        self::assertStringContainsString(' | ', $linkHtml);

        $artist->setMember($member);
        self::assertSame($member, $artist->getMember());
        self::assertTrue($member->getArtist()->contains($artist));

        $info = new EventArtistInfo();
        $info->setArtist($artist);
        self::assertTrue($artist->getEventArtistInfos()->contains($info));
        $artist->setCreatedAt(new \DateTimeImmutable('2030-01-02 10:00:00'));
        self::assertSame('2030-01-02 10:00:00', $artist->getCreatedAt()->format('Y-m-d H:i:s'));
        $info->removeArtist();
        $artist->removeEventArtistInfo($info);
        self::assertFalse($artist->getEventArtistInfos()->contains($info));
        $artist->clearEventArtistInfos();
        self::assertCount(0, $artist->getEventArtistInfos());
    }

    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }
}
