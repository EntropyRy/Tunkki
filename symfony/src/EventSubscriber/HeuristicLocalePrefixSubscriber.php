<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Event;
use App\Repository\EventRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;

/**
 * HeuristicLocalePrefixSubscriber
 *
 * Refactored:
 *  - Dynamically derives terminal localized segments from the RouteCollection.
 *  - No hard-coded list of "shop", "kauppa", etc.
 *  - Still enforces canonical rules:
 *      * English localized endpoints must reside under /en/...
 *      * Finnish localized endpoints must NOT reside under /en/...
 *
 * Approach:
 *  1. At first invocation, scan the RouteCollection:
 *       - For every route whose name ends with ".fi" or ".en", extract the last
 *         literal (non-placeholder) segment of its path (if any).
 *       - Build two sets: $fiTerminals, $enTerminals.
 *  2. For incoming paths shaped like "/year/slug/segment" (with optional "/en" prefix),
 *     if "segment" appears in one language set, enforce prefix rules heuristically.
 *
 * Fallback DB Validation:
 *  - Optionally confirm the {year}/{slug} pair corresponds to a real Event.
 *    (Skippable via CANONICAL_HEURISTIC_SKIP_DB_CHECK=1)
 *
 * Headers on redirect:
 *  X-Locale-Canonicalization: heuristic_enforce_en_prefix|heuristic_strip_en_prefix
 *  X-Locale-Canonicalization-From / -To
 *  X-Locale-Canonicalization-Phase: heuristic-pre-route
 */
final class HeuristicLocalePrefixSubscriber implements EventSubscriberInterface
{
    private bool $debug;
    private bool $skipDbCheck;

    /** @var string[] */
    private static array $fiTerminals = [];
    /** @var string[] */
    private static array $enTerminals = [];
    private static bool $terminalsInitialized = false;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ?EventRepository $eventRepository = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->debug = (bool) ($_SERVER['CANONICAL_LOCALE_DEBUG'] ?? (getenv('CANONICAL_LOCALE_DEBUG') ?: false));
        $this->skipDbCheck = (bool) ($_SERVER['CANONICAL_HEURISTIC_SKIP_DB_CHECK'] ?? (getenv('CANONICAL_HEURISTIC_SKIP_DB_CHECK') ?: false));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequestHeuristic', 80],
        ];
    }

    public function onKernelRequestHeuristic(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo() ?? '/';

        if ($this->shouldSkip($path, $request->getMethod())) {
            return;
        }

        $this->initTerminals();

        // We only act on paths shaped like (optionally /en)/(year)/(slug)/(terminal)
        // where (terminal) appears in at least one of the localized terminal sets.
        if (!preg_match('#^(?:/en)?/\d{4}/[^/]+/[^/]+$#', $path)) {
            return;
        }

        $hasEnPrefix = str_starts_with($path, '/en/');
        $trimmed = $hasEnPrefix ? substr($path, 4) : substr($path, 1); // remove leading "/en/" or "/"
        if ($trimmed === false) {
            return;
        }
        $segments = explode('/', $trimmed);
        if (count($segments) < 3) {
            return;
        }

        [$yearStr, $slug, $terminal] = array_slice($segments, 0, 3);
        if (!ctype_digit($yearStr)) {
            return;
        }
        $year = (int) $yearStr;
        if (!$this->isPlausibleYear($year)) {
            return;
        }

        // Validate event existence if required
        if (
            !$this->skipDbCheck &&
            !$this->eventExistsForYearSlug($year, $slug)
        ) {
            return;
        }

        $isFiTerminal = \in_array($terminal, self::$fiTerminals, true);
        $isEnTerminal = \in_array($terminal, self::$enTerminals, true);

        if (!$isFiTerminal && !$isEnTerminal) {
            return; // Not a recognized localized terminal
        }

        // EN case: if terminal is English and no /en prefix -> redirect add prefix
        if ($isEnTerminal && !$hasEnPrefix) {
            $target = '/en' . $path;
            $this->issueRedirect(
                $event,
                $path,
                $target,
                'heuristic_enforce_en_prefix',
            );
            return;
        }

        // FI case: if terminal is Finnish and has /en prefix -> strip it
        if ($isFiTerminal && $hasEnPrefix) {
            $target = substr($path, 3);
            if ($target === '') {
                $target = '/';
            }
            $this->issueRedirect(
                $event,
                $path,
                $target,
                'heuristic_strip_en_prefix',
            );
            return;
        }

        // Already canonical or ambiguous (e.g. both sets contain terminal — rare)
    }

    private function initTerminals(): void
    {
        if (self::$terminalsInitialized) {
            return;
        }
        self::$fiTerminals = [];
        self::$enTerminals = [];

        $collection = $this->router->getRouteCollection();
        if ($collection === null) {
            self::$terminalsInitialized = true;
            return;
        }

        /** @var string $name */
        foreach ($collection->getIterator() as $name => $route) {
            if (!($route instanceof Route)) {
                continue;
            }
            if (!preg_match('/\.(fi|en)$/', $name, $m)) {
                continue;
            }
            $locale = $m[1]; // fi or en
            $path = rtrim($route->getPath(), '/');
            if ($path === '') {
                continue;
            }
            $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
            if ($parts === []) {
                continue;
            }
            // Find last literal (not placeholder) segment
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $seg = $parts[$i];
                if ($seg === '' || $seg[0] === '{') {
                    continue;
                }
                if ($locale === 'fi') {
                    self::$fiTerminals[] = $seg;
                } else {
                    self::$enTerminals[] = $seg;
                }
                break;
            }
        }

        // Deduplicate
        self::$fiTerminals = array_values(array_unique(self::$fiTerminals));
        self::$enTerminals = array_values(array_unique(self::$enTerminals));
        self::$terminalsInitialized = true;

        $this->log('terminals_initialized', [
            'fi' => self::$fiTerminals,
            'en' => self::$enTerminals,
        ]);
    }

    private function issueRedirect(
        RequestEvent $event,
        string $from,
        string $to,
        string $kind,
    ): void {
        if ($to === $from) {
            return;
        }
        $this->log($kind, ['from' => $from, 'to' => $to]);

        $resp = new RedirectResponse($to, 301);
        $resp->headers->set('X-Locale-Canonicalization', $kind);
        $resp->headers->set('X-Locale-Canonicalization-From', $from);
        $resp->headers->set('X-Locale-Canonicalization-To', $to);
        $resp->headers->set('X-Locale-Canonicalization-Phase', 'heuristic-pre-route');
        $event->setResponse($resp);
    }

    private function shouldSkip(string $path, string $method): bool
    {
        if (!\in_array($method, ['GET', 'HEAD'], true)) {
            return true;
        }

        return
            str_starts_with($path, '/_profiler') ||
            str_starts_with($path, '/_wdt') ||
            str_starts_with($path, '/_error') ||
            str_starts_with($path, '/_fragment') ||
            str_starts_with($path, '/assets/') ||
            str_starts_with($path, '/build/') ||
            str_starts_with($path, '/media/') ||
            str_starts_with($path, '/api/');
    }

    private function isPlausibleYear(int $year): bool
    {
        $nowYear = (int) date('Y');
        return $year >= ($nowYear - 1) && $year <= ($nowYear + 2);
    }

    private function eventExistsForYearSlug(int $year, string $slug): bool
    {
        if (!$this->eventRepository) {
            return false;
        }
        /** @var Event|null $event */
        $event = $this->eventRepository->findOneBy(['url' => $slug]);
        if (!$event) {
            return false;
        }
        $date = $event->getEventDate();
        return $date !== null && ((int) $date->format('Y')) === $year;
    }

    private function log(string $msg, array $context = []): void
    {
        if (!$this->debug || !$this->logger) {
            return;
        }
        $this->logger->debug('[heuristic_locale_prefix] ' . $msg, $context);
    }
}
