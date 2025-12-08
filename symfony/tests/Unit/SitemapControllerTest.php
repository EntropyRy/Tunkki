<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\SitemapController;
use App\Repository\EventRepository;
use App\Repository\MenuRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for SitemapController::index().
 *
 * We do not boot the Symfony kernel. Instead we:
 *  - Mock the injected repositories.
 *  - Provide lightweight stub Event objects exposing only the methods used.
 *  - Subclass the controller to intercept renderView() and capture the computed
 *    $urls array (the core output we want to validate) before it is serialized
 *    into XML via the Twig template.
 *
 * The controller logic we exercise:
 *  - Iteration of events -> building 'urls' entries with alt links.
 *  - Ensuring each event produces a 'loc', 'lastmod', 'changefreq', 'priority', 'alts'.
 *  - Reuse/reset semantics of the $alt array between successive events (regression guard).
 *  - Response headers include text/xml content type.
 *
 * We skip menu root node coverage here (roots array empty) to isolate the event
 * path logic. A second test covers multiple events to ensure $alt data does not
 * leak between iterations.
 */
final class SitemapControllerTest extends TestCase
{
    /**
     * Build a minimal stub event exposing only methods used by controller.
     */
    private function makeStubEvent(
        string $fiUrl,
        string $enUrl,
        \DateTimeImmutable $updatedAt,
    ): object {
        return new class($fiUrl, $enUrl, $updatedAt) {
            public function __construct(
                private string $fiUrl,
                private string $enUrl,
                private \DateTimeImmutable $updatedAt,
            ) {
            }

            public function getUrlByLang(string $lang): string
            {
                return 'fi' === $lang ? $this->fiUrl : $this->enUrl;
            }

            public function getUpdatedAt(): \DateTimeImmutable
            {
                return $this->updatedAt;
            }
        };
    }

    /**
     * Create a testable controller that captures the 'urls' parameter.
     *
     * @param EventRepository&MockObject $eventRepo
     * @param MenuRepository&MockObject  $menuRepo
     */
    private function makeController(
        EventRepository $eventRepo,
        MenuRepository $menuRepo,
        array &$capturedUrls,
    ): SitemapController {
        return new class($eventRepo, $menuRepo, $capturedUrls) extends SitemapController {
            public function __construct(
                EventRepository $eventRepo,
                MenuRepository $menuRepo,
                private array &$captured,
            ) {
                parent::__construct($eventRepo, $menuRepo);
            }

            protected function renderView(string $view, array $parameters = []): string
            {
                $this->captured = $parameters['urls'] ?? [];

                // Return minimal valid XML snippet to satisfy Response usage.
                return '<urlset/>';
            }
        };
    }

    public function testSingleEventGeneratesExpectedUrlStructure(): void
    {
        /** @var EventRepository&MockObject $eventRepo */
        $eventRepo = $this->createMock(EventRepository::class);
        /** @var MenuRepository&MockObject $menuRepo */
        $menuRepo = $this->createMock(MenuRepository::class);

        $updated = new \DateTimeImmutable('2025-01-01 12:00:00');
        $event = $this->makeStubEvent(
            'https://entropy.fi/2025/fi-event',
            'https://entropy.fi/en/2025/en-event',
            $updated
        );

        $eventRepo->expects(self::once())
            ->method('getSitemapEvents')
            ->willReturn([$event]);

        $menuRepo->expects(self::once())
            ->method('getRootNodes')
            ->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $captured);

        $request = Request::create('http://localhost/sitemap.xml', 'GET');
        $response = $controller->index($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/xml', $response->headers->get('Content-Type'), 'Response must be XML.');

        // Validate captured urls array
        self::assertCount(1, $captured, 'Exactly one URL entry expected.');
        $entry = $captured[0];

        foreach (['loc', 'lastmod', 'changefreq', 'priority', 'alts'] as $key) {
            self::assertArrayHasKey($key, $entry, "Entry missing key '{$key}'.");
        }

        self::assertSame($event->getUrlByLang('fi'), $entry['loc'], 'loc should use Finnish URL.');
        self::assertSame($updated->format('Y-m-d'), $entry['lastmod']);
        self::assertSame('weekly', $entry['changefreq']);
        self::assertSame('0.5', $entry['priority']);

        self::assertIsArray($entry['alts']);
        self::assertSame($event->getUrlByLang('fi'), $entry['alts']['fi']);
        self::assertSame($event->getUrlByLang('en'), $entry['alts']['en']);
    }

    public function testMultipleEventsDoNotCrossPollinateAltData(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = $this->createStub(MenuRepository::class);

        $eventA = $this->makeStubEvent(
            'https://entropy.fi/2025/a-fi',
            'https://entropy.fi/en/2025/a-en',
            new \DateTimeImmutable('2025-02-01')
        );
        $eventB = $this->makeStubEvent(
            'https://entropy.fi/2025/b-fi',
            'https://entropy.fi/en/2025/b-en',
            new \DateTimeImmutable('2025-03-10')
        );

        $eventRepo->method('getSitemapEvents')->willReturn([$eventA, $eventB]);
        $menuRepo->method('getRootNodes')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $captured);

        $controller->index(Request::create('http://localhost/sitemap.xml', 'GET'));

        self::assertCount(2, $captured, 'Two entries expected for two events.');

        $first = $captured[0];
        $second = $captured[1];

        // Ensure each alt set corresponds only to its own event
        self::assertSame($eventA->getUrlByLang('fi'), $first['alts']['fi']);
        self::assertSame($eventA->getUrlByLang('en'), $first['alts']['en']);

        self::assertSame($eventB->getUrlByLang('fi'), $second['alts']['fi']);
        self::assertSame($eventB->getUrlByLang('en'), $second['alts']['en']);

        // Sanity: entries differ
        self::assertNotSame($first['alts']['fi'], $second['alts']['fi']);
    }
}
