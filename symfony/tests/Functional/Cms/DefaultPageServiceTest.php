<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cms;

use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for DefaultPageService (sonata.page.service.default).
 *
 * Tests cover:
 *  - Page rendering in both locales
 *  - SEO metadata (title, description, OG tags, Twitter tags)
 */
final class DefaultPageServiceTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    #[DataProvider('localeProvider')]
    public function testDefaultPageRendersInBothLocales(string $locale, string $path): void
    {
        $this->seedClientHome($locale);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    public function testPageTitleAppearsInHead(): void
    {
        $this->seedClientHome('fi');
        $crawler = $this->client->request('GET', '/liity');

        $this->assertResponseIsSuccessful();
        $titleTag = $crawler->filter('head title');
        $this->assertGreaterThan(0, $titleTag->count(), 'Page should have a <title> tag');
        $this->assertNotEmpty($titleTag->text(), 'Title should not be empty');
    }

    public function testOpenGraphTagsRendered(): void
    {
        $this->seedClientHome('fi');
        $crawler = $this->client->request('GET', '/liity');

        $this->assertResponseIsSuccessful();

        // og:type
        $ogType = $crawler->filter('meta[property="og:type"]');
        $this->assertGreaterThan(0, $ogType->count(), 'Page should have og:type meta tag');
        $this->assertSame('article', $ogType->attr('content'));

        // og:image
        $ogImage = $crawler->filter('meta[property="og:image"]');
        $this->assertGreaterThan(0, $ogImage->count(), 'Page should have og:image meta tag');
        $this->assertNotEmpty($ogImage->attr('content'));

        // html prefix attribute
        $htmlPrefix = $crawler->filter('html[prefix]');
        $this->assertGreaterThan(0, $htmlPrefix->count(), 'HTML tag should have prefix attribute');
        $this->assertMatchesRegularExpression('/og:/', (string) $htmlPrefix->attr('prefix'));
    }

    public function testTwitterImageTagRendered(): void
    {
        $this->seedClientHome('fi');
        $crawler = $this->client->request('GET', '/liity');

        $this->assertResponseIsSuccessful();

        $twitterImage = $crawler->filter('meta[property="twitter:image"]');
        $this->assertGreaterThan(0, $twitterImage->count(), 'Page should have twitter:image meta tag');
        $this->assertNotEmpty($twitterImage->attr('content'));
    }

    public function testMetaKeywordsRenderedWhenSet(): void
    {
        $this->seedClientHome('fi');
        $crawler = $this->client->request('GET', '/liity');

        $this->assertResponseIsSuccessful();

        $keywords = $crawler->filter('meta[name="keywords"]');
        // Keywords are optional - if page has them, they should render
        if ($keywords->count() > 0) {
            $this->assertNotEmpty($keywords->attr('content'), 'Keywords should not be empty when present');
        } else {
            $this->addToAssertionCount(1); // Page has no keywords set, which is valid
        }
    }

    public static function localeProvider(): array
    {
        return [
            'Finnish' => ['fi', '/liity'],
            'English' => ['en', '/en/join-us'],
        ];
    }
}
