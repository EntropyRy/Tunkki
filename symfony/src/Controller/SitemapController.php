<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use App\Repository\EventRepository;
use App\Repository\MenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    private const array LOCALES = ['fi', 'en'];

    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly MenuRepository $menuRepo,
        #[Autowire(service: 'cmf_routing.router')]
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(['fi' => '/sitemap.xml'], name: 'sitemap')]
    public function index(): Response
    {
        $urls = array_merge(
            $this->buildEventUrls(),
            $this->buildMenuUrls(),
        );

        $response = new Response(
            $this->renderView('sitemap.html.twig', ['urls' => $urls]),
            200
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }

    /**
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string, alts: array<string,string>}>
     */
    private function buildEventUrls(): array
    {
        $urls = [];

        foreach ($this->eventRepo->getSitemapEvents() as $event) {
            $alts = $this->buildEventAlternates($event);
            if ([] === $alts) {
                continue;
            }

            $urls[] = [
                'loc' => $alts['fi'],
                'lastmod' => $event->getUpdatedAt()->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.5',
                'alts' => $alts,
            ];
        }

        return $urls;
    }

    /**
     * @return array<string,string>
     */
    private function buildEventAlternates(Event $event): array
    {
        // External events: same destination for both languages.
        if ($event->getExternalUrl()) {
            $url = trim((string) $event->getUrl());
            if ('' === $url) {
                return [];
            }

            return [
                'fi' => $url,
            ];
        }

        // Prefer slug route when available.
        $slug = trim((string) $event->getUrl());
        if ('' !== $slug) {
            $year = $event->getEventDate()->format('Y');

            return [
                'fi' => $this->urlGenerator->generate('entropy_event_slug', [
                    'year' => $year,
                    'slug' => $slug,
                    '_locale' => 'fi',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'en' => $this->urlGenerator->generate('entropy_event_slug', [
                    'year' => $year,
                    'slug' => $slug,
                    '_locale' => 'en',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        // Fallback to ID route.
        $id = $event->getId();

        return [
            'fi' => $this->urlGenerator->generate('entropy_event', [
                'id' => $id,
                '_locale' => 'fi',
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'en' => $this->urlGenerator->generate('entropy_event', [
                'id' => $id,
                '_locale' => 'en',
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /**
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string, alts: array<string,string>}>
     */
    private function buildMenuUrls(): array
    {
        $urls = [];
        foreach ($this->menuRepo->getRootNodes() as $root) {
            $this->appendMenuUrls(
                item: $root,
                urls: $urls,
                depth: 1,
                maxDepth: 3,
            );
        }

        return $urls;
    }

    /**
     * @param array<int, array{loc: string, lastmod: string, changefreq: string, priority: string, alts: array<string,string>}> $urls
     */
    private function appendMenuUrls(
        Menu $item,
        array &$urls,
        int $depth,
        int $maxDepth,
    ): void {
        if ($depth > $maxDepth) {
            return;
        }

        if (1 !== $depth && !$item->getEnabled()) {
            return;
        }

        $entry = $this->buildMenuUrlEntry($item);
        if (null !== $entry) {
            $urls[] = $entry;
        }

        foreach ($item->getChildren() as $child) {
            $this->appendMenuUrls(
                item: $child,
                urls: $urls,
                depth: $depth + 1,
                maxDepth: $maxDepth,
            );
        }
    }

    /**
     * @return array{loc: string, lastmod: string, changefreq: string, priority: string, alts: array<string,string>}|null
     */
    private function buildMenuUrlEntry(Menu $item): ?array
    {
        $alts = [];

        foreach (self::LOCALES as $locale) {
            $url = $this->resolveMenuItemUrl($item, $locale);
            if (null === $url) {
                return null;
            }
            $alts[$locale] = $url;
        }

        $pageFi = $item->getPageByLang('fi');
        if (!$pageFi instanceof SonataPagePage || !$pageFi->getUpdatedAt() instanceof \DateTimeInterface) {
            return null;
        }

        return [
            'loc' => $alts['fi'],
            'lastmod' => $pageFi->getUpdatedAt()->format('Y-m-d'),
            'changefreq' => 'weekly',
            'priority' => '0.5',
            'alts' => $alts,
        ];
    }

    private function resolveMenuItemUrl(Menu $item, string $locale): ?string
    {
        $page = $item->getPageByLang($locale);
        $pathOrUrl = $page?->getUrl() ?: $item->getUrl();
        $pathOrUrl = trim((string) $pathOrUrl);

        if ('' === $pathOrUrl || '#' === $pathOrUrl) {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        $path = str_starts_with($pathOrUrl, '/') ? $pathOrUrl : '/'.$pathOrUrl;

        // Use the CMS site's relative path (e.g. "/en") instead of leaking locale through query params.
        $siteRelativePath = $page?->getSite()?->getRelativePath();
        $siteRelativePath = trim((string) $siteRelativePath);
        if ('' !== $siteRelativePath && !str_starts_with($siteRelativePath, '/')) {
            $siteRelativePath = '/'.$siteRelativePath;
        }
        if ('' !== $siteRelativePath && !str_starts_with($path, $siteRelativePath.'/') && $path !== $siteRelativePath) {
            $path = rtrim($siteRelativePath, '/').$path;
        }

        return $this->urlGenerator->generate('page_slug', [
            'path' => $path,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
