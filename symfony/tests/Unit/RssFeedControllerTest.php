<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\RssFeedController;
use App\Repository\EventRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for RssFeedController::index().
 *
 * We do not boot the Symfony kernel or render Twig templates. Instead we:
 *  - Mock the EventRepository.
 *  - Subclass the controller to override renderView(), capturing the template
 *    name and parameters (events + locale) and returning a controlled XML stub.
 *  - Assert the response headers and that the correct data flows into the view.
 *
 * This keeps the test focused on controller wiring (repository call, locale
 * propagation, response preparation) without caring about Twig / entity details.
 */
final class RssFeedControllerTest extends TestCase
{
    /**
     * Lightweight event stub; the controller never inspects the events directly,
     * it only forwards them to the template. We keep it minimal.
     */
    private function makeEventStub(string $id): object
    {
        return (object) ['_id' => $id];
    }

    /**
     * Build a testable controller overriding renderView to capture parameters.
     *
     * @param EventRepository&MockObject $repo
     * @param array                      $capture Reference to array for capturing data
     */
    private function makeController(EventRepository $repo, array &$capture): RssFeedController
    {
        return new class($repo, $capture) extends RssFeedController {
            public function __construct(
                private EventRepository $repo,
                private array &$capture,
            ) {
            }

            protected function renderView(string $view, array $parameters = []): string
            {
                $this->capture = [
                    'view' => $view,
                    'events' => $parameters['events'] ?? null,
                    'locale' => $parameters['locale'] ?? null,
                ];

                // Minimal valid RSS-like skeleton for Response body
                return '<rss><channel/></rss>';
            }

            // Expose original index method but inject our mock repository
            public function callIndex(Request $request): Response
            {
                return parent::index($request, $this->repo);
            }
        };
    }

    public function testIndexPassesEventsAndLocaleAndSetsXmlHeader(): void
    {
        /** @var EventRepository&MockObject $repo */
        $repo = $this->createMock(EventRepository::class);

        $events = [
            $this->makeEventStub('e1'),
            $this->makeEventStub('e2'),
        ];

        $repo->expects(self::once())
            ->method('getRSSEvents')
            ->willReturn($events);

        $captured = [];
        $controller = $this->makeController($repo, $captured);

        $request = Request::create('/en-feed.rss', 'GET');
        $request->setLocale('en');

        $response = $controller->callIndex($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(
            'application/xml; charset=utf-8',
            $response->headers->get('Content-Type'),
            'Expected XML content type.'
        );
        self::assertSame('<rss><channel/></rss>', $response->getContent(), 'Render output stub should be returned.');

        // Validate captured view parameters
        self::assertSame('rss_feed/index.xml.twig', $captured['view']);
        self::assertSame($events, $captured['events'], 'Events array should be forwarded unchanged.');
        self::assertSame('en', $captured['locale'], 'Locale should be forwarded.');
    }

    public function testIndexWithFinnishLocale(): void
    {
        /** @var EventRepository&MockObject $repo */
        $repo = $this->createMock(EventRepository::class);

        $event = $this->makeEventStub('fi1');
        $repo->method('getRSSEvents')->willReturn([$event]);

        $captured = [];
        $controller = $this->makeController($repo, $captured);

        $request = Request::create('/feed.rss', 'GET');
        $request->setLocale('fi');

        $controller->callIndex($request);

        self::assertSame('fi', $captured['locale'], 'Finnish locale should propagate.');
        self::assertCount(1, $captured['events']);
    }
}
