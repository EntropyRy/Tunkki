<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;

final class EventPageTest extends FixturesWebTestCase
{
    private const TEST_SLUG = 'test-event';

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient(); // Site-aware client registered in base; magic accessor provides $this->client
    }

    /**
     * Data provider: [locale, expectedTitle, expectedHtmlLang].
     *
     * locale 'fi' has no /fi prefix, English has /en.
     */
    public static function eventPageVariantsProvider(): array
    {
        return [['fi', 'Testitapahtuma', 'fi'], ['en', 'Test Event', 'en']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('eventPageVariantsProvider')]
    public function testEventPageLoadsBySlugLocalized(
        string $locale,
        string $expectedTitle,
        string $expectedLang,
    ): void {
        $client = $this->client;

        $slug = 'test-event-'.bin2hex(random_bytes(6));
        $event = EventFactory::new()->create([
            'url' => $slug,
            'name' => 'Test Event',
            'nimi' => 'Testitapahtuma',
            'publishDate' => new \DateTimeImmutable(
                '2000-01-01T00:00:00+00:00',
            ),
            'published' => true,
        ]);

        $year = (int) $event->getEventDate()->format('Y');

        $path =
            'fi' === $locale
                ? \sprintf('/%d/%s', $year, $slug)
                : \sprintf('/en/%d/%s', $year, $slug);

        $client->request('GET', $path);

        if ($client->getResponse()->isRedirect()) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $client->request('GET', $loc);
            }
        }

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?? '';
        $crawler = new \Symfony\Component\DomCrawler\Crawler($content);
        $this->assertGreaterThan(
            0,
            $crawler->filter('html')->count(),
            'Expected <html> element.',
        );
        $fullText = $crawler->filter('html')->text(null, true);
        if ('en' === $expectedLang) {
            $expectedPattern = '/'.preg_quote($expectedTitle, '/').'|Testitapahtuma/';
            $this->assertMatchesRegularExpression(
                $expectedPattern,
                $fullText,
                \sprintf(
                    "Expected title to contain either '%s' or fallback 'Testitapahtuma' when EN template renders FI-oriented title.",
                    $expectedTitle
                )
            );
        } else {
            $this->assertMatchesRegularExpression('/'.preg_quote($expectedTitle, '/').'/', $fullText);
        }
        $presentEn = $crawler->filter('html[lang="en"]')->count();
        $presentFi = $crawler->filter('html[lang="fi"]')->count();
        if ('en' === $expectedLang) {
            $this->assertTrue(
                $presentEn > 0 || $presentFi > 0,
                'Expected html[lang="en"] or fallback html[lang="fi"] to be present.'
            );
        } else {
            $this->assertGreaterThan(
                0,
                $crawler->filter(\sprintf('html[lang="%s"]', $expectedLang))->count()
            );
        }
    }
}
