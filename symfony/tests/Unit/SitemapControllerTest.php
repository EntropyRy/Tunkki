<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\SitemapController;
use App\Entity\Event;
use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Repository\EventRepository;
use App\Repository\MenuRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
    private function makeEvent(
        ?int $id,
        ?string $url,
        bool $externalUrl,
        \DateTimeImmutable $eventDate,
        \DateTimeImmutable $updatedAt,
    ): Event {
        $event = new Event();
        $event->setUrl($url);
        $event->setExternalUrl($externalUrl);
        $event->setUpdatedAt($updatedAt);

        // EventDate is initialized in constructor, but we override for test determinism.
        $event->setEventDate($eventDate);

        if (null !== $id) {
            $ref = new \ReflectionProperty(Event::class, 'id');
            $ref->setAccessible(true);
            $ref->setValue($event, $id);
        }

        return $event;
    }

    private function makePage(string $url, ?\DateTimeInterface $updatedAt = null): SonataPagePage
    {
        $page = new SonataPagePage();
        $page->setUrl($url);
        if (null !== $updatedAt) {
            $page->setUpdatedAt($updatedAt);
        }

        return $page;
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
        UrlGeneratorInterface $urlGenerator,
        array &$capturedUrls,
    ): SitemapController {
        return new class($eventRepo, $menuRepo, $urlGenerator, $capturedUrls) extends SitemapController {
            public function __construct(
                EventRepository $eventRepo,
                MenuRepository $menuRepo,
                UrlGeneratorInterface $urlGenerator,
                private array &$captured,
            ) {
                parent::__construct($eventRepo, $menuRepo, $urlGenerator);
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
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $updated = new \DateTimeImmutable('2025-01-01 12:00:00');
        $eventDate = new \DateTimeImmutable('2025-05-20 12:00:00');
        $event = $this->makeEvent(
            id: 123,
            url: 'fi-event-slug',
            externalUrl: false,
            eventDate: $eventDate,
            updatedAt: $updated,
        );

        $eventRepo->expects(self::once())
            ->method('getSitemapEvents')
            ->willReturn([$event]);

        $menuRepo->expects(self::once())
            ->method('getRootNodes')
            ->willReturn([]);

        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                $locale = (string) ($parameters['_locale'] ?? 'fi');
                $year = (string) ($parameters['year'] ?? '');
                $slug = (string) ($parameters['slug'] ?? '');
                $id = (string) ($parameters['id'] ?? '');

                if ('entropy_event_slug' === $route) {
                    return "https://example.test/{$locale}/{$year}/{$slug}";
                }
                if ('entropy_event' === $route) {
                    return "https://example.test/{$locale}/event/{$id}";
                }

                return "https://example.test/{$route}";
            }
        );

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);

        $response = $controller->index();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/xml', $response->headers->get('Content-Type'), 'Response must be XML.');

        // Validate captured urls array
        self::assertCount(1, $captured, 'Exactly one URL entry expected.');
        $entry = $captured[0];

        foreach (['loc', 'lastmod', 'changefreq', 'priority', 'alts'] as $key) {
            self::assertArrayHasKey($key, $entry, "Entry missing key '{$key}'.");
        }

        $expectedYear = $eventDate->format('Y');
        self::assertSame("https://example.test/fi/{$expectedYear}/fi-event-slug", $entry['loc'], 'loc should use Finnish URL.');
        self::assertSame($updated->format('Y-m-d'), $entry['lastmod']);
        self::assertSame('weekly', $entry['changefreq']);
        self::assertSame('0.5', $entry['priority']);

        self::assertIsArray($entry['alts']);
        self::assertSame("https://example.test/fi/{$expectedYear}/fi-event-slug", $entry['alts']['fi']);
        self::assertSame("https://example.test/en/{$expectedYear}/fi-event-slug", $entry['alts']['en']);
    }

    public function testExternalEventWithDestinationUsesSameUrlForBothLocales(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = $this->createStub(MenuRepository::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $event = $this->makeEvent(
            id: 10,
            url: 'https://example.com/external',
            externalUrl: true,
            eventDate: new \DateTimeImmutable('2025-05-20'),
            updatedAt: new \DateTimeImmutable('2025-01-02'),
        );

        $eventRepo->method('getSitemapEvents')->willReturn([$event]);
        $menuRepo->method('getRootNodes')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        self::assertCount(1, $captured);
        self::assertSame('https://example.com/external', $captured[0]['loc']);
        self::assertSame('https://example.com/external', $captured[0]['alts']['fi']);
        self::assertArrayNotHasKey('en', $captured[0]['alts']);
    }

    public function testExternalEventWithoutDestinationIsSkipped(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = $this->createStub(MenuRepository::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $event = $this->makeEvent(
            id: 11,
            url: '',
            externalUrl: true,
            eventDate: new \DateTimeImmutable('2025-05-20'),
            updatedAt: new \DateTimeImmutable('2025-01-02'),
        );

        $eventRepo->method('getSitemapEvents')->willReturn([$event]);
        $menuRepo->method('getRootNodes')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        self::assertSame([], $captured);
    }

    public function testInternalEventWithoutSlugFallsBackToIdRoute(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = $this->createStub(MenuRepository::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = []): string {
                $locale = (string) ($parameters['_locale'] ?? 'fi');
                if ('entropy_event' === $route) {
                    return "https://example.test/{$locale}/event/{$parameters['id']}";
                }

                return 'https://example.test/other';
            }
        );

        $event = $this->makeEvent(
            id: 99,
            url: null,
            externalUrl: false,
            eventDate: new \DateTimeImmutable('2025-05-20'),
            updatedAt: new \DateTimeImmutable('2025-01-02'),
        );

        $eventRepo->method('getSitemapEvents')->willReturn([$event]);
        $menuRepo->method('getRootNodes')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        self::assertCount(1, $captured);
        self::assertSame('https://example.test/fi/event/99', $captured[0]['loc']);
        self::assertSame('https://example.test/en/event/99', $captured[0]['alts']['en']);
    }

    public function testMenuUrlsSkipNullEntriesDisabledChildrenAndDepthOverLimit(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository&MockObject $menuRepo */
        $menuRepo = $this->createMock(MenuRepository::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = []): string {
                if ('page_slug' !== $route) {
                    return 'https://example.test/other';
                }

                $locale = (string) ($parameters['_locale'] ?? 'fi');
                $path = ltrim((string) ($parameters['path'] ?? ''), '/');

                return "https://example.test/{$locale}/{$path}";
            }
        );

        $root = (new Menu())
            ->setLabel('Root')
            ->setNimi('Root')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->makePage('/root', new \DateTimeImmutable('2025-01-01')))
            ->setPageEn($this->makePage('/root', new \DateTimeImmutable('2025-01-01')));

        // Enabled child => included.
        $childIncluded = (new Menu())
            ->setLabel('Child Included')
            ->setNimi('Child Included')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->makePage('/child', new \DateTimeImmutable('2025-01-02')))
            ->setPageEn($this->makePage('/child', new \DateTimeImmutable('2025-01-02')));
        $root->addChild($childIncluded);

        // Disabled child at depth>1 => skipped.
        $childDisabled = (new Menu())
            ->setLabel('Child Disabled')
            ->setNimi('Child Disabled')
            ->setEnabled(false)
            ->setPosition(2)
            ->setPageFi($this->makePage('/disabled', new \DateTimeImmutable('2025-01-02')))
            ->setPageEn($this->makePage('/disabled', new \DateTimeImmutable('2025-01-02')));
        $root->addChild($childDisabled);

        // Level 3 included (depth=3).
        $level3 = (new Menu())
            ->setLabel('Level 3')
            ->setNimi('Level 3')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->makePage('/level3', new \DateTimeImmutable('2025-01-03')))
            ->setPageEn($this->makePage('/level3', new \DateTimeImmutable('2025-01-03')));
        $childIncluded->addChild($level3);

        // Depth=4 should be ignored by maxDepth=3.
        $level4 = (new Menu())
            ->setLabel('Level 4')
            ->setNimi('Level 4')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->makePage('/level4', new \DateTimeImmutable('2025-01-04')))
            ->setPageEn($this->makePage('/level4', new \DateTimeImmutable('2025-01-04')));
        $level3->addChild($level4);

        // Child that resolves to null url ('#') => entry null (but recursion continues).
        $childNull = (new Menu())
            ->setLabel('Child Null')
            ->setNimi('Child Null')
            ->setEnabled(true)
            ->setPosition(3)
            ->setUrl('#');
        $root->addChild($childNull);

        $menuRepo->expects(self::once())->method('getRootNodes')->willReturn([$root]);
        $eventRepo->method('getSitemapEvents')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        $locs = array_map(static fn (array $entry) => $entry['loc'], $captured);

        self::assertContains('https://example.test/fi/root', $locs);
        self::assertContains('https://example.test/fi/child', $locs);
        self::assertContains('https://example.test/fi/level3', $locs);

        self::assertNotContains('https://example.test/fi/disabled', $locs);
        self::assertNotContains('https://example.test/fi/level4', $locs);
    }

    public function testMenuItemWithoutFinnishUpdatedAtIsSkipped(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository&MockObject $menuRepo */
        $menuRepo = $this->createMock(MenuRepository::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $urlGenerator->method('generate')->willReturn('https://example.test/fi/yhdistys');

        $root = (new Menu())
            ->setLabel('Root')
            ->setNimi('Root')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->makePage('/yhdistys', null))
            ->setPageEn($this->makePage('/association', new \DateTimeImmutable('2025-01-02')));

        $menuRepo->expects(self::once())->method('getRootNodes')->willReturn([$root]);
        $eventRepo->method('getSitemapEvents')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        self::assertSame([], $captured, 'Menu item without Finnish updatedAt should be skipped.');
    }

    public function testMenuItemExternalUrlIsReturnedAsIs(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository&MockObject $menuRepo */
        $menuRepo = $this->createMock(MenuRepository::class);
        /** @var UrlGeneratorInterface&MockObject $urlGenerator */
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $urlGenerator->expects(self::never())->method('generate');

        $pageFi = $this->makePage('https://example.com/fi', new \DateTimeImmutable('2025-01-01'));
        $pageEn = $this->makePage('https://example.com/en', new \DateTimeImmutable('2025-01-01'));

        $root = (new Menu())
            ->setLabel('Root')
            ->setNimi('Root')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($pageFi)
            ->setPageEn($pageEn);

        $menuRepo->expects(self::once())->method('getRootNodes')->willReturn([$root]);
        $eventRepo->method('getSitemapEvents')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        self::assertCount(1, $captured);
        self::assertSame('https://example.com/fi', $captured[0]['loc']);
        self::assertSame('https://example.com/fi', $captured[0]['alts']['fi']);
        self::assertSame('https://example.com/en', $captured[0]['alts']['en']);
    }

    public function testMenuItemUsesSiteRelativePathEvenIfMissingLeadingSlash(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository&MockObject $menuRepo */
        $menuRepo = $this->createMock(MenuRepository::class);
        /** @var UrlGeneratorInterface&MockObject $urlGenerator */
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $generatedPaths = [];
        $urlGenerator->expects(self::exactly(2))
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH) use (&$generatedPaths): string {
                self::assertSame('page_slug', $route);
                self::assertSame(UrlGeneratorInterface::ABSOLUTE_URL, $referenceType);
                $generatedPaths[] = $parameters['path'] ?? null;

                return match ($parameters['path'] ?? null) {
                    '/association' => 'https://example.test/fi/association',
                    '/en/association' => 'https://example.test/en/association',
                    default => 'https://example.test/unexpected',
                };
            });

        $siteFi = new SonataPageSite();
        $siteFi->setRelativePath(''); // Finnish: no prefix

        $siteEn = new SonataPageSite();
        $siteEn->setRelativePath('en'); // missing leading slash on purpose (should become "/en")

        $pageFi = $this->makePage('/association', new \DateTimeImmutable('2025-01-01'));
        $pageFi->setSite($siteFi);

        $pageEn = $this->makePage('/association', new \DateTimeImmutable('2025-01-01'));
        $pageEn->setSite($siteEn);

        $root = (new Menu())
            ->setLabel('Root')
            ->setNimi('Root')
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($pageFi)
            ->setPageEn($pageEn);

        $menuRepo->expects(self::once())->method('getRootNodes')->willReturn([$root]);
        $eventRepo->method('getSitemapEvents')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);
        $controller->index();

        self::assertCount(1, $captured);
        self::assertSame('https://example.test/fi/association', $captured[0]['loc']);
        self::assertSame(['/association', '/en/association'], $generatedPaths);
    }

    public function testMultipleEventsDoNotCrossPollinateAltData(): void
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = $this->createStub(EventRepository::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = $this->createStub(MenuRepository::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                $locale = (string) ($parameters['_locale'] ?? 'fi');
                $year = (string) ($parameters['year'] ?? '');
                $slug = (string) ($parameters['slug'] ?? '');

                return "https://example.test/{$locale}/{$year}/{$slug}";
            }
        );

        $eventA = $this->makeEvent(
            id: 1,
            url: 'a-slug',
            externalUrl: false,
            eventDate: new \DateTimeImmutable('2025-02-01'),
            updatedAt: new \DateTimeImmutable('2025-02-02'),
        );
        $eventB = $this->makeEvent(
            id: 2,
            url: 'b-slug',
            externalUrl: false,
            eventDate: new \DateTimeImmutable('2025-03-10'),
            updatedAt: new \DateTimeImmutable('2025-03-11'),
        );

        $eventRepo->method('getSitemapEvents')->willReturn([$eventA, $eventB]);
        $menuRepo->method('getRootNodes')->willReturn([]);

        $captured = [];
        $controller = $this->makeController($eventRepo, $menuRepo, $urlGenerator, $captured);

        $controller->index();

        self::assertCount(2, $captured, 'Two entries expected for two events.');

        $first = $captured[0];
        $second = $captured[1];

        // Ensure each alt set corresponds only to its own event
        self::assertSame('https://example.test/fi/2025/a-slug', $first['alts']['fi']);
        self::assertSame('https://example.test/en/2025/a-slug', $first['alts']['en']);

        self::assertSame('https://example.test/fi/2025/b-slug', $second['alts']['fi']);
        self::assertSame('https://example.test/en/2025/b-slug', $second['alts']['en']);

        // Sanity: entries differ
        self::assertNotSame($first['alts']['fi'], $second['alts']['fi']);
    }
}
