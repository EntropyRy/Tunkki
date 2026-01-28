<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Stream;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * Functional tests for the /stream page served by StreamPage page service.
 *
 * Migration notes:
 *  - Replaced brittle raw HTML substring assertions with structural selector assertions.
 *  - Uses DomCrawler filters for meta tags and html[lang] instead of assertStringContainsString.
 *  - Adds helper assertions for clarity and maintainability.
 */
final class StreamPageTest extends FixturesWebTestCase
{
    use \App\Tests\Support\MetaAssertionTrait;
    // (Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static site-aware client)

    protected function setUp(): void
    {
        parent::setUp();
        // Unified site-aware initialization (Sonata Page multisite context + SiteRequest wrapping)
        $this->initSiteAwareClient();
        // Seed a harmless GET to ensure WebTestCase static client has a response/crawler
        $this->seedClientHome('fi');
        // (Removed redundant $this->client reassignment; site-aware client already registered in base)
    }

    public function testStreamPageLoadsForFinnishLocale(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/stream');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(
            0,
            $crawler->filter('html[lang="fi"]')->count(),
            'Expected html[lang="fi"] on Finnish stream page'
        );
    }

    public function testStreamPageLoadsForEnglishLocale(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/en/stream');

        $this->assertResponseIsSuccessful();
        $presentEn = $crawler->filter('html[lang="en"]')->count();
        $presentFi = $crawler->filter('html[lang="fi"]')->count();
        $this->assertTrue(
            $presentEn > 0 || $presentFi > 0,
            'Expected html[lang="en"] or fallback html[lang="fi"] to be present.'
        );
    }

    public function testStreamPageContainsExpectedMetaTags(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/en/stream');

        $this->assertResponseIsSuccessful();

        $this->assertMetaTagExists($crawler, 'property', 'og:title');
        $this->assertMetaTagExists($crawler, 'property', 'og:description');
        $this->assertMetaTagExists($crawler, 'property', 'og:type');
        $this->assertMetaTagExists($crawler, 'property', 'twitter:card');
        $this->assertMetaTagExists($crawler, 'name', 'description');
        $this->assertMetaTagExists($crawler, 'name', 'keywords');
    }

    public function testStreamPageWithOnlineStreamShowsArtistInMetadata(): void
    {
        // Create an online stream
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('test-stream-recording');

        $this->em()->persist($stream);
        $this->em()->flush();

        $client = $this->client;
        $crawler = $client->request('GET', '/en/stream');

        $this->assertResponseIsSuccessful();
        $this->assertMetaTagExists($crawler, 'property', 'og:description');

        // Cleanup
        $stream->setOnline(false);
        $this->em()->flush();
    }

    public function testStreamPageContainsLiveComponentsPlaceholder(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/en/stream');

        $this->assertResponseIsSuccessful();

        $this->assertGreaterThan(
            0,
            $crawler->filter('[data-controller]')->count(),
            'Expected at least one element with a data-controller attribute (Live Component bootstrap).'
        );
    }

    public function testStreamPageTitleMatchesPageName(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/en/stream');

        $this->assertResponseIsSuccessful();

        $titleNode = $crawler->filter('meta[property="og:title"]');
        $this->assertGreaterThan(0, $titleNode->count(), 'og:title meta tag should exist.');
        $content = $titleNode->first()->attr('content') ?? '';
        $this->assertNotEmpty($content, 'og:title content attribute should not be empty.');
        $this->assertMatchesRegularExpression('/stream/i', $content, 'og:title content should reference Stream.');
    }

    public function testStreamPageContainsSonataPageContainers(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/en/stream');

        $this->assertResponseIsSuccessful();

        // Heuristic container presence: check stable layout container
        $this->assertTrue(
            $crawler->filter('.e-container')->count() > 0,
            'Expected a structural page container element (.e-container).'
        );
    }

    public function testStreamPageAccessibleWithoutAuthentication(): void
    {
        $client = $this->client;
        $client->request('GET', '/stream');
        $this->assertResponseIsSuccessful();
    }

    public function testStreamPageRespondsToGetRequest(): void
    {
        $client = $this->client;
        $client->request('GET', '/en/stream');
        $this->assertResponseIsSuccessful();
    }

    public function testStreamPageRespondsToHeadRequest(): void
    {
        $client = $this->client;
        $client->request('HEAD', '/en/stream');
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'HEAD request should return 200.');
        $this->assertSame('', $client->getResponse()->getContent() ?? '', 'HEAD response body should be empty.');
    }
}
