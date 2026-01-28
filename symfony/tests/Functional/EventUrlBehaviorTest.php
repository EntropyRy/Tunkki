<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * Event URL bilingual & external passthrough behavior tests.
 *
 * Coverage Goals (Roadmap Tasks #40 / H):
 *  - Internal (non-external) events expose distinct Finnish (no /en prefix) and English (/en prefix) paths.
 *  - Page content locale (html[lang]) matches path locale.
 *  - Event names (Finnish vs English) appear on their respective localized pages.
 *  - External events: getUrlByLang() returns the raw external URL for both locales (passthrough).
 *  - External event does NOT expose a working internal localized page (either not found or redirects out).
 *
 * Tolerance:
 *  Existing tests use patterns:
 *    FI: /{year}/{slug}
 *    EN: /en/{year}/{slug}
 *  If implementation later introduces segment (e.g. /en/event/{year}/{slug} or /tapahtuma/{year}/{slug}),
 *  the regex assertions accept both shapes.
 *
 * NOTE:
 *  This test mixes functional HTTP assertions (for internal events) with pure entity-level method assertions
 *  (getUrlByLang for external passthrough) to avoid binding to fragile routing for externally hosted events.
 */
final class EventUrlBehaviorTest extends FixturesWebTestCase
{
    use \App\Tests\Support\UniqueValueTrait;
    // Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static registration.

    protected function setUp(): void
    {
        parent::setUp();
        // Use unified site-aware initialization so Sonata Page multisite context is consistent.
        $this->initSiteAwareClient(); // Site-aware client registered in base; $this->client resolved via magic __get.
    }

    /**
     * @return array<array{locale:string}>
     */
    public static function localeProvider(): array
    {
        return [['fi'], ['en']];
    }

    /**
     * Internal (non-external) event localized path & content behavior.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function testInternalEventLocalizedPathsAndContent(
        string $locale,
    ): void {
        // Create deterministic bilingual event
        $slug = $this->uniqueSlug('bilingual-event');
        $event = EventFactory::new()->create([
            'url' => $slug,
            'name' => 'English Title Event',
            'nimi' => 'Suomenkielinen Tapahtuma',
            'publishDate' => new \DateTimeImmutable(
                '2000-01-01T00:00:00+00:00',
            ),
        ]);

        $year = $event->getEventDate()->format('Y');

        // Build candidate path (current implementation pattern)
        $path =
            'fi' === $locale
                ? \sprintf('/%s/%s', $year, $slug)
                : \sprintf('/en/%s/%s', $year, $slug);

        $tried = [];
        $this->client->request('GET', $path);
        $tried[] = $path;

        // Fallback tolerance: If 404, try alternative (future-proof if route style changes to /en/event/)
        $usedPath = $path;
        if (404 === $this->client->getResponse()->getStatusCode()) {
            $alt =
                'fi' === $locale
                    ? \sprintf('/tapahtuma/%s/%s', $year, $slug)
                    : \sprintf('/en/event/%s/%s', $year, $slug);
            $this->client->request('GET', $alt);
            $tried[] = $alt;
            $usedPath = $alt;
        }

        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            if ('' !== $loc) {
                $this->client->request('GET', $loc);
                $tried[] = $loc;
                $usedPath = $loc;
                $status = $this->client->getResponse()->getStatusCode();
            }
        }
        self::assertSame(
            200,
            $status,
            \sprintf(
                'Expected 200 for %s localized event path (used: %s; tried: %s), got %d.',
                $locale,
                $usedPath,
                implode(' -> ', $tried),
                $status,
            ),
        );

        // Locale attribute
        $content = $this->client->getResponse()->getContent() ?? '';
        $crawler = new \Symfony\Component\DomCrawler\Crawler($content);
        self::assertGreaterThan(
            0,
            $crawler->filter(\sprintf('html[lang="%s"]', $locale))->count(),
            'Expected html[lang] to exist for the localized event page.',
        );

        // Title presence via structural selector (data-test attribute on event title headings)
        $expectedFragment =
            'fi' === $locale
                ? 'Suomenkielinen Tapahtuma'
                : 'English Title Event';

        self::assertGreaterThan(
            0,
            $crawler->filter('.event-name')->count(),
            'Expected .event-name element to exist on event page.',
        );
        $eventNameText = $crawler->filter('.event-name')->text(null, true);
        self::assertMatchesRegularExpression(
            '/'.preg_quote($expectedFragment, '/').'/',
            $eventNameText,
        );
    }

    public function testExternalEventUrlPassthrough(): void
    {
        $target = 'https://example.org/external-test-destination';
        $event = EventFactory::new()->external($target)->create();

        // Entity-level guarantee: getUrlByLang should return raw external URL for *both* locales.
        foreach (['fi', 'en'] as $loc) {
            $url = $event->getUrlByLang($loc);
            self::assertNotNull(
                $url,
                'getUrlByLang returned null unexpectedly.',
            );
            self::assertStringStartsWith(
                'https://',
                $url,
                'External event URL must be absolute.',
            );
            self::assertSame(
                $target,
                $url,
                'External passthrough must return the original target URL.',
            );
        }

        // Attempt to guess an internal localized path (using slug logic would be impossible because URL field is absolute).
        // We assert that hitting a derived internal-style path does NOT yield a localized event page 200
        // (acceptable outcomes: 404, 302 to external URL, or direct external redirect).
        $year = $event->getEventDate()->format('Y');
        // Derive a pseudo "slug" from external domain (defensive)
        $pseudoSlug = 'external-test-destination';

        $fiPath = \sprintf('/%s/%s', $year, $pseudoSlug);
        $this->client->request('GET', $fiPath);
        $fiStatus = $this->client->getResponse()->getStatusCode();

        // If it redirects, ensure it's *not* to an internal localized page but to the external target
        if (\in_array($fiStatus, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            self::assertSame(
                $target,
                $loc,
                'External event internal-style FI path should redirect directly to external target.',
            );
        } else {
            self::assertContains(
                $fiStatus,
                [302, 301, 404],
                'Unexpected status for external event internal FI-style path.',
            );
        }

        $enPath = \sprintf('/en/%s/%s', $year, $pseudoSlug);
        $this->client->request('GET', $enPath);
        $enStatus = $this->client->getResponse()->getStatusCode();
        if (\in_array($enStatus, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            self::assertSame(
                $target,
                $loc,
                'External event internal-style EN path should redirect directly to external target.',
            );
        } else {
            self::assertContains(
                $enStatus,
                [302, 301, 404],
                'Unexpected status for external event internal EN-style path.',
            );
        }
    }
}
