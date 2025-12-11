<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sonata\PageBundle\CmsManager\CmsManagerSelectorInterface;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension that provides localized URL generation for both regular routes and Sonata Page Bundle pages.
 *
 * This extension handles the complexities of URL generation in a multilingual setup where:
 * - Finnish (fi) is the default locale with no URL prefix
 * - English (en) has a /en URL prefix
 * - Sonata Page Bundle pages use technical aliases with locale suffixes (e.g., _page_alias_services_fi, _page_alias_services_en)
 * - Menu entities can link pages in different locales together
 *
 * The extension uses multiple strategies to find localized pages (in priority order):
 * 1. Menu-based lookup (primary method) - Uses Menu entity relationships
 * 2. Technical alias transformation (fallback method) - Pattern-based alias matching
 * 3. Basic URL path transformation (final fallback) - Simple URL manipulation
 */
class LocalizedUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsManagerSelectorInterface $cmsManagerSelector,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('localized_url', $this->getLocalizedUrl(...)),
            new TwigFunction('localized_route', $this->getLocalizedRoute(...)),
        ];
    }

    /**
     * Generates a localized URL for the current page or a specific page.
     *
     * This method uses multiple strategies to find the localized version:
     * 1. If a page object is provided, uses Menu-based lookup first
     * 2. Falls back to technical alias transformation if Menu method fails
     * 3. For regular routes, uses route name transformation
     * 4. Final fallback uses URL path manipulation
     *
     * @param string             $targetLocale The target locale ('fi' or 'en')
     * @param PageInterface|null $page         Optional page object. If provided, will find the localized version of this page.
     *
     * @return string The localized URL
     */
    public function getLocalizedUrl(
        string $targetLocale,
        PageInterface|int|null $page = null,
    ): string {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return '/';
        }

        // If an integer id is provided, resolve the page entity
        if (\is_int($page)) {
            $resolved = $this->entityManager
                ->getRepository(SonataPagePage::class)
                ->find($page);
            $page = $resolved instanceof PageInterface ? $resolved : null;
        }

        // If a page object (resolved or given) is provided, use it to find the localized version
        if ($page instanceof PageInterface) {
            return $this->getLocalizedUrlFromPage($page, $targetLocale);
        }

        $currentPath = $request->getPathInfo();
        $currentRoute = $request->attributes->get('_route');

        // Handle root path
        if ('/' === $currentPath || '/en' === $currentPath) {
            return 'en' === $targetLocale ? '/en' : '/';
        }

        // Check if current route is a Sonata Page route (they start with 'page_')
        if ($currentRoute && str_starts_with((string) $currentRoute, 'page_')) {
            // Try to find the current page by route name and get its localized version
            $currentPage = $this->findPageByRouteName($currentRoute);
            if ($currentPage instanceof PageInterface) {
                return $this->getLocalizedUrlFromPage(
                    $currentPage,
                    $targetLocale,
                );
            }

            // Fallback for Sonata pages
            return 'en' === $targetLocale ? '/en' : '/';
        }

        // If we have a route, try to get its localized version
        if ($currentRoute) {
            // Strip locale suffix if present
            $baseRoute = preg_replace(
                '/\.(en|fi)$/',
                '',
                (string) $currentRoute,
            );
            $targetRoute = $baseRoute.'.'.$targetLocale;

            // Get route parameters from the current request
            $routeParams = $request->attributes->get('_route_params', []);

            try {
                // Prefer structural base route + _locale generation; fall back to suffixed targetRoute.
                $baseRoute ??= preg_replace('/\.(en|fi)$/', '', (string) $currentRoute);
                $generated = null;

                // 1. Attempt base route + _locale (works with SiteAwareRouter alias logic).
                try {
                    $generated = $this->router->generate(
                        $baseRoute,
                        array_merge($routeParams, ['_locale' => $targetLocale])
                    );
                } catch (\Throwable) {
                    // ignore and try suffixed variant
                }

                // 2. If base attempt failed, try explicit suffixed route name.
                if (null === $generated) {
                    $generated = $this->router->generate($targetRoute, $routeParams);
                }

                return $this->formatUrlForLocale($generated, $targetLocale);
            } catch (\Throwable) {
                // Fallback: structural transformation of current path
                $pathWithoutPrefix = str_starts_with($currentPath, '/en')
                    ? substr($currentPath, 3)
                    : $currentPath;

                return $this->formatUrlForLocale($pathWithoutPrefix, $targetLocale);
            }
        }

        // Fallback to default handling
        $pathWithoutPrefix = str_starts_with($currentPath, '/en')
            ? substr($currentPath, 3)
            : $currentPath;

        return 'en' === $targetLocale
            ? '/en'.$pathWithoutPrefix
            : $pathWithoutPrefix;
    }

    /**
     * Generates a localized URL from a specific Sonata Page object.
     *
     * This method uses a two-step approach:
     * 1. First searches through Menu entities to find linked pages in different locales
     * 2. If that fails, tries technical alias transformation (e.g., _page_alias_services_fi -> _page_alias_services_en)
     *
     * The Menu-based lookup is the primary method as it's more reliable and leverages
     * the existing Menu structure that properly links pages across locales.
     *
     * @param PageInterface $page         The source page object
     * @param string        $targetLocale The target locale ('fi' or 'en')
     *
     * @return string The localized URL
     */
    private function getLocalizedUrlFromPage(
        PageInterface $page,
        string $targetLocale,
    ): string {
        // First try the Menu-based approach (primary method)
        $targetPage = $this->findPageThroughMenu($page, $targetLocale);
        if ($targetPage && $targetPage->getEnabled()) {
            $url = $targetPage->getUrl();
            if ($url) {
                $this->logger?->debug(
                    'LocalizedUrlExtension: Found page via Menu lookup',
                    [
                        'source_page_id' => $this->getPageIdSafely($page),
                        'target_locale' => $targetLocale,
                        'target_page_id' => $this->getPageIdSafely($targetPage),
                        'strategy' => 'menu_lookup',
                    ],
                );

                // Handle the URL based on locale and prefix requirements
                return $this->formatUrlForLocale($url, $targetLocale);
            }
        }

        // If Menu approach fails, try the technical alias approach as fallback
        $pageAlias = $page->getPageAlias();
        // Extract the base alias and current locale from technical alias
        // Expected format: _page_alias_services_fi or _page_alias_services_en
        if (
            $pageAlias
            && preg_match('/^(.+)_(fi|en)$/', $pageAlias, $matches)
        ) {
            $baseAlias = $matches[1];
            $targetAlias = $baseAlias.'_'.$targetLocale;
            // Find the page with the target alias
            $targetPage = $this->findPageByAlias($targetAlias);
            if ($targetPage && $targetPage->getEnabled()) {
                $url = $targetPage->getUrl();
                if ($url) {
                    $this->logger?->debug(
                        'LocalizedUrlExtension: Found page via technical alias',
                        [
                            'source_alias' => $pageAlias,
                            'target_alias' => $targetAlias,
                            'target_locale' => $targetLocale,
                            'strategy' => 'technical_alias',
                        ],
                    );

                    // Handle the URL based on locale and prefix requirements
                    return $this->formatUrlForLocale($url, $targetLocale);
                }
            }
        }

        // Fallback if we can't find the localized page
        $this->logger?->debug(
            'LocalizedUrlExtension: Using fallback URL generation',
            [
                'source_page_id' => $this->getPageIdSafely($page),
                'target_locale' => $targetLocale,
                'strategy' => 'fallback',
            ],
        );

        return 'en' === $targetLocale ? '/en' : '/';
    }

    /**
     * Finds a Sonata Page by its route name using CmsManager.
     *
     * @param string             $routeName The route name to search for
     * @param SiteInterface|null $site      The site to search in (optional, will use current site if not provided)
     *
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageByRouteName(string $routeName, ?SiteInterface $site = null): ?PageInterface
    {
        try {
            if (!$site instanceof SiteInterface) {
                $request = $this->requestStack->getCurrentRequest();
                if (!$request instanceof Request) {
                    return null;
                }
                // Get site from request attributes (set by Sonata Page Bundle)
                $site = $request->attributes->get('site');
                if (!$site instanceof SiteInterface) {
                    return null;
                }
            }

            $cmsManager = $this->cmsManagerSelector->retrieve();

            return $cmsManager->getPageByRouteName($site, $routeName);
        } catch (\Throwable) {
            // Page not found or other error
            return null;
        }
    }

    /**
     * Finds a Sonata Page by its technical alias using CmsManager.
     *
     * @param string             $alias The technical alias to search for (e.g., '_page_alias_services_fi')
     * @param SiteInterface|null $site  The site to search in (optional, will use current site if not provided)
     *
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageByAlias(string $alias, ?SiteInterface $site = null): ?PageInterface
    {
        try {
            if (!$site instanceof SiteInterface) {
                $request = $this->requestStack->getCurrentRequest();
                if (!$request instanceof Request) {
                    return null;
                }
                // Get site from request attributes (set by Sonata Page Bundle)
                $site = $request->attributes->get('site');
                if (!$site instanceof SiteInterface) {
                    return null;
                }
            }

            $cmsManager = $this->cmsManagerSelector->retrieve();

            return $cmsManager->getPageByPageAlias($site, $alias);
        } catch (\Throwable) {
            // Page not found or other error
            return null;
        }
    }

    /**
     * Finds the corresponding page in the target locale through the Menu system.
     *
     * This is the primary method for finding localized pages because:
     * 1. Menu entities explicitly link pages across locales via pageFi and pageEn properties
     * 2. It's more reliable than technical alias patterns which may not be consistent
     * 3. It leverages the existing content management structure
     * 4. It works even when pages don't follow naming conventions
     *
     * This method searches for a menu item that references the given page (in either locale)
     * and then returns the page for the target locale from the same menu item.
     *
     * Note: Uses try-catch to handle SnapshotPageProxy objects which may cause comparison issues
     * during Doctrine's findOneBy operations.
     *
     * @param PageInterface $page         The source page object
     * @param string        $targetLocale The target locale ('fi' or 'en')
     *
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageThroughMenu(
        PageInterface $page,
        string $targetLocale,
    ): ?PageInterface {
        $menuRepository = $this->entityManager->getRepository(Menu::class);

        // 1. Try direct object comparison first (works when $page is a managed SonataPagePage entity)
        try {
            $menuItem = $menuRepository->findOneBy(['pageFi' => $page]);
            if (null === $menuItem) {
                $menuItem = $menuRepository->findOneBy(['pageEn' => $page]);
            }
            if (null !== $menuItem) {
                return $menuItem->getPageByLang($targetLocale);
            }
        } catch (\Throwable $e) {
            // Ignore and fallback to ID-based resolution
            $this->logger?->debug(
                'LocalizedUrlExtension: direct menu lookup failed, falling back to ID-based lookup',
                [
                    'exception' => $e::class,
                ],
            );
        }

        // 2. Fallback: resolve by numeric ID (handles SnapshotPageProxy which cannot be compared directly)
        $pageIdString = $this->getPageIdSafely($page);
        if (ctype_digit($pageIdString)) {
            try {
                $pid = (int) $pageIdString;
                $qb = $menuRepository
                    ->createQueryBuilder('m')
                    ->leftJoin('m.pageFi', 'pf')
                    ->leftJoin('m.pageEn', 'pe')
                    ->andWhere('pf.id = :pid OR pe.id = :pid')
                    ->setParameter('pid', $pid)
                    ->setMaxResults(1);

                $menuItem = $qb->getQuery()->getOneOrNullResult();
                if (null !== $menuItem) {
                    $this->logger?->debug(
                        'LocalizedUrlExtension: menu item found via ID-based lookup',
                        [
                            'page_id' => $pid,
                            'target_locale' => $targetLocale,
                        ],
                    );

                    return $menuItem->getPageByLang($targetLocale);
                }
            } catch (\Throwable $e) {
                $this->logger?->debug(
                    'LocalizedUrlExtension: ID-based menu lookup failed',
                    [
                        'page_id' => $pageIdString,
                        'exception' => $e::class,
                    ],
                );
            }
        } else {
            $this->logger?->debug(
                'LocalizedUrlExtension: cannot perform ID-based lookup, unknown page id',
            );
        }

        return null;
    }

    /**
     * Formats a URL according to locale prefix requirements.
     *
     * @param string $url          The base URL
     * @param string $targetLocale The target locale
     *
     * @return string The formatted URL
     */
    private function formatUrlForLocale(
        string $url,
        string $targetLocale,
    ): string {
        // Absolute URL guard: if already fully qualified (http/https),
        // skip locale prefix manipulation (only normalize trailing slash).
        if (1 === preg_match('#^https?://#i', $url)) {
            if ('/' !== $url && str_ends_with($url, '/')) {
                $url = rtrim($url, '/');
            }

            return $url;
        }

        // Normalize trailing slash (except for root)
        if ('/' !== $url && str_ends_with($url, '/')) {
            $url = rtrim($url, '/');
        }

        if ('en' === $targetLocale) {
            if ('/' === $url) {
                return '/en';
            }
            if (!str_starts_with($url, '/en')) {
                $url = '/en'.$url;
            }
        } elseif ('/en' === $url) {
            // fi
            $url = '/';
        } elseif (str_starts_with($url, '/en/')) {
            $url = substr($url, 3) ?: '/';
        }

        if ('' === $url) {
            $url = '/';
        }

        return $url;
    }

    /**
     * Safely gets the page ID as a string, handling proxy objects that may not have getId().
     *
     * @param PageInterface $page The page object
     *
     * @return string The page ID as string or 'unknown' if not available
     */
    private function getPageIdSafely(PageInterface $page): string
    {
        try {
            $id = $page->getId();

            return null !== $id ? (string) $id : 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Generate a localized URL based on a specified route and parameters.
     *
     * @param string $route        The route name without locale suffix
     * @param string $targetLocale The target locale (e.g., 'en', 'fi')
     * @param array  $parameters   The route parameters
     *
     * @return string The generated URL
     */
    public function getLocalizedRoute(
        string $route,
        string $targetLocale,
        array $parameters = [],
    ): string {
        // Strip any existing locale suffix if present
        $baseRoute = preg_replace('/\.(en|fi)$/', '', $route);
        $targetRoute = $baseRoute.'.'.$targetLocale;

        try {
            // Prefer base route + forced _locale first
            $generated = null;
            try {
                $generated = $this->router->generate(
                    $baseRoute,
                    array_merge($parameters, ['_locale' => $targetLocale])
                );
            } catch (\Throwable) {
                // ignore and try suffixed
            }

            if (null === $generated) {
                $generated = $this->router->generate($targetRoute, $parameters);
            }

            return $this->formatUrlForLocale($generated, $targetLocale);
        } catch (\Throwable) {
            return $this->formatUrlForLocale('/', $targetLocale);
        }
    }
}
