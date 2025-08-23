<?php

namespace App\Twig;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sonata\PageBundle\Model\PageInterface;
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
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction("localized_url", $this->getLocalizedUrl(...)),
            new TwigFunction(
                "localized_url_debug",
                $this->getLocalizedUrlWithDebug(...),
            ),
            new TwigFunction("localized_route", $this->getLocalizedRoute(...)),
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
     * @param string $targetLocale The target locale ('fi' or 'en')
     * @param PageInterface|null $page Optional page object. If provided, will find the localized version of this page.
     * @return string The localized URL
     */
    public function getLocalizedUrl(
        string $targetLocale,
        PageInterface|int|null $page = null,
    ): string {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return "/";
        }

        // If an integer id is provided, resolve the page entity
        if (is_int($page)) {
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
        $currentRoute = $request->attributes->get("_route");

        // Handle root path
        if ($currentPath === "/" || $currentPath === "/en") {
            return $targetLocale === "en" ? "/en" : "/";
        }

        // Check if current route is a Sonata Page route (they start with 'page_')
        if ($currentRoute && str_starts_with((string) $currentRoute, "page_")) {
            // Try to find the current page by route name and get its localized version
            $currentPage = $this->findPageByRouteName($currentRoute);
            if ($currentPage instanceof PageInterface) {
                return $this->getLocalizedUrlFromPage(
                    $currentPage,
                    $targetLocale,
                );
            }
            // Fallback for Sonata pages
            return $targetLocale === "en" ? "/en" : "/";
        }

        // If we have a route, try to get its localized version
        if ($currentRoute) {
            // Strip locale suffix if present
            $baseRoute = preg_replace(
                '/\.(en|fi)$/',
                "",
                (string) $currentRoute,
            );
            $targetRoute = $baseRoute . "." . $targetLocale;

            // Get route parameters from the current request
            $routeParams = $request->attributes->get("_route_params", []);

            try {
                // Generate URL with the current parameters
                $url = $this->router->generate($targetRoute, $routeParams);

                // For English locale, ensure /en prefix
                if ($targetLocale === "en" && !str_starts_with($url, "/en")) {
                    return "/en" . $url;
                }

                // For Finnish locale, remove /en prefix if present
                if ($targetLocale === "fi" && str_starts_with($url, "/en")) {
                    return substr($url, 3);
                }

                return $url;
            } catch (\Throwable) {
                // Fallback if route generation fails
                $pathWithoutPrefix = str_starts_with($currentPath, "/en")
                    ? substr($currentPath, 3)
                    : $currentPath;
                return $targetLocale === "en"
                    ? "/en" . $pathWithoutPrefix
                    : $pathWithoutPrefix;
            }
        }

        // Fallback to default handling
        $pathWithoutPrefix = str_starts_with($currentPath, "/en")
            ? substr($currentPath, 3)
            : $currentPath;
        return $targetLocale === "en"
            ? "/en" . $pathWithoutPrefix
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
     * @param PageInterface $page The source page object
     * @param string $targetLocale The target locale ('fi' or 'en')
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
                    "LocalizedUrlExtension: Found page via Menu lookup",
                    [
                        "source_page_id" => $this->getPageIdSafely($page),
                        "target_locale" => $targetLocale,
                        "target_page_id" => $this->getPageIdSafely($targetPage),
                        "strategy" => "menu_lookup",
                    ],
                );

                // Handle the URL based on locale and prefix requirements
                if ($targetLocale === "en" && !str_starts_with($url, "/en")) {
                    return "/en" . $url;
                }
                if ($targetLocale === "fi" && str_starts_with($url, "/en")) {
                    return substr($url, 3) ?: "/";
                }
                return $url;
            }
        }

        // If Menu approach fails, try the technical alias approach as fallback
        $pageAlias = $page->getPageAlias();
        // Extract the base alias and current locale from technical alias
        // Expected format: _page_alias_services_fi or _page_alias_services_en
        if (
            $pageAlias &&
            preg_match('/^(.+)_(fi|en)$/', $pageAlias, $matches)
        ) {
            $baseAlias = $matches[1];
            $targetAlias = $baseAlias . "_" . $targetLocale;
            // Find the page with the target alias
            $targetPage = $this->findPageByAlias($targetAlias);
            if ($targetPage && $targetPage->getEnabled()) {
                $url = $targetPage->getUrl();
                if ($url) {
                    $this->logger?->debug(
                        "LocalizedUrlExtension: Found page via technical alias",
                        [
                            "source_alias" => $pageAlias,
                            "target_alias" => $targetAlias,
                            "target_locale" => $targetLocale,
                            "strategy" => "technical_alias",
                        ],
                    );

                    // Handle the URL based on locale and prefix requirements
                    if (
                        $targetLocale === "en" &&
                        !str_starts_with($url, "/en")
                    ) {
                        return "/en" . $url;
                    }
                    if (
                        $targetLocale === "fi" &&
                        str_starts_with($url, "/en")
                    ) {
                        return substr($url, 3) ?: "/";
                    }
                    return $url;
                }
            }
        }

        // Fallback if we can't find the localized page
        $this->logger?->debug(
            "LocalizedUrlExtension: Using fallback URL generation",
            [
                "source_page_id" => $this->getPageIdSafely($page),
                "target_locale" => $targetLocale,
                "strategy" => "fallback",
            ],
        );

        return $targetLocale === "en" ? "/en" : "/";
    }

    /**
     * Finds a Sonata Page by its route name.
     *
     * @param string $routeName The route name to search for
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageByRouteName(string $routeName): ?PageInterface
    {
        $repository = $this->entityManager->getRepository(
            SonataPagePage::class,
        );
        return $repository->findOneBy([
            "routeName" => $routeName,
            "enabled" => true,
        ]);
    }

    /**
     * Finds a Sonata Page by its technical alias.
     *
     * @param string $alias The technical alias to search for (e.g., '_page_alias_services_fi')
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageByAlias(string $alias): ?PageInterface
    {
        $repository = $this->entityManager->getRepository(
            SonataPagePage::class,
        );
        return $repository->findOneBy([
            "pageAlias" => $alias,
            "enabled" => true,
        ]);
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
     * @param PageInterface $page The source page object
     * @param string $targetLocale The target locale ('fi' or 'en')
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageThroughMenu(
        PageInterface $page,
        string $targetLocale,
    ): ?PageInterface {
        $menuRepository = $this->entityManager->getRepository(Menu::class);

        // 1. Try direct object comparison first (works when $page is a managed SonataPagePage entity)
        try {
            $menuItem = $menuRepository->findOneBy(["pageFi" => $page]);
            if ($menuItem === null) {
                $menuItem = $menuRepository->findOneBy(["pageEn" => $page]);
            }
            if ($menuItem !== null) {
                return $menuItem->getPageByLang($targetLocale);
            }
        } catch (\Throwable $e) {
            // Ignore and fallback to ID-based resolution
            $this->logger?->debug(
                "LocalizedUrlExtension: direct menu lookup failed, falling back to ID-based lookup",
                [
                    "exception" => $e::class,
                ],
            );
        }

        // 2. Fallback: resolve by numeric ID (handles SnapshotPageProxy which cannot be compared directly)
        $pageIdString = $this->getPageIdSafely($page);
        if (ctype_digit($pageIdString)) {
            try {
                $pid = (int) $pageIdString;
                $qb = $menuRepository
                    ->createQueryBuilder("m")
                    ->leftJoin("m.pageFi", "pf")
                    ->leftJoin("m.pageEn", "pe")
                    ->andWhere("pf.id = :pid OR pe.id = :pid")
                    ->setParameter("pid", $pid)
                    ->setMaxResults(1);

                $menuItem = $qb->getQuery()->getOneOrNullResult();
                if ($menuItem !== null) {
                    $this->logger?->debug(
                        "LocalizedUrlExtension: menu item found via ID-based lookup",
                        [
                            "page_id" => $pid,
                            "target_locale" => $targetLocale,
                        ],
                    );
                    return $menuItem->getPageByLang($targetLocale);
                }
            } catch (\Throwable $e) {
                $this->logger?->debug(
                    "LocalizedUrlExtension: ID-based menu lookup failed",
                    [
                        "page_id" => $pageIdString,
                        "exception" => $e::class,
                    ],
                );
            }
        } else {
            $this->logger?->debug(
                "LocalizedUrlExtension: cannot perform ID-based lookup, unknown page id",
            );
        }

        return null;
    }

    /**
     * Debug version of getLocalizedUrl that returns both URL and strategy information.
     * Useful for debugging which strategy was used to generate the URL.
     *
     * @param string $targetLocale The target locale ('fi' or 'en')
     * @param PageInterface|null $page Optional page object
     * @return array{url: string, strategy: string, debug_info: array} URL and debug information
     */
    public function getLocalizedUrlWithDebug(
        string $targetLocale,
        PageInterface|int|null $page = null,
    ): array {
        $inputWasId = is_int($page);
        $resolvedPage = $page;

        if ($inputWasId) {
            $resolvedPage = $this->entityManager
                ->getRepository(SonataPagePage::class)
                ->find($page);
        }

        $debugInfo = [
            "target_locale" => $targetLocale,
            "input_was_id" => $inputWasId,
            "has_page_object" => $resolvedPage instanceof PageInterface,
            "strategies_tried" => [],
        ];

        if ($resolvedPage instanceof PageInterface) {
            $pageAlias = $resolvedPage->getPageAlias();
            $debugInfo["page_alias"] = $pageAlias;
            $debugInfo["source_page_id"] = $this->getPageIdSafely(
                $resolvedPage,
            );

            // Try menu lookup first (primary method)
            $debugInfo["strategies_tried"][] = "menu_lookup";
            $targetPage = $this->findPageThroughMenu(
                $resolvedPage,
                $targetLocale,
            );
            if (
                $targetPage &&
                $targetPage->getEnabled() &&
                $targetPage->getUrl()
            ) {
                $url = $this->formatUrlForLocale(
                    $targetPage->getUrl(),
                    $targetLocale,
                );
                return [
                    "url" => $url,
                    "strategy" => "menu_lookup",
                    "debug_info" => array_merge($debugInfo, [
                        "target_page_id" => $this->getPageIdSafely($targetPage),
                    ]),
                ];
            }

            // Try technical alias as fallback
            if (
                $pageAlias &&
                preg_match('/^(.+)_(fi|en)$/', $pageAlias, $matches)
            ) {
                $debugInfo["strategies_tried"][] = "technical_alias";
                $baseAlias = $matches[1];
                $targetAlias = $baseAlias . "_" . $targetLocale;
                $targetPage = $this->findPageByAlias($targetAlias);

                if (
                    $targetPage &&
                    $targetPage->getEnabled() &&
                    $targetPage->getUrl()
                ) {
                    $url = $this->formatUrlForLocale(
                        $targetPage->getUrl(),
                        $targetLocale,
                    );
                    return [
                        "url" => $url,
                        "strategy" => "technical_alias",
                        "debug_info" => array_merge($debugInfo, [
                            "target_alias" => $targetAlias,
                            "target_page_id" => $this->getPageIdSafely(
                                $targetPage,
                            ),
                        ]),
                    ];
                }
            }
        }

        // Fallback to regular URL generation (will resolve id again but cheap)
        $debugInfo["strategies_tried"][] = "fallback";
        $url = $this->getLocalizedUrl($targetLocale, $resolvedPage);

        return [
            "url" => $url,
            "strategy" => "fallback",
            "debug_info" => $debugInfo,
        ];
    }

    /**
     * Formats a URL according to locale prefix requirements.
     *
     * @param string $url The base URL
     * @param string $targetLocale The target locale
     * @return string The formatted URL
     */
    private function formatUrlForLocale(
        string $url,
        string $targetLocale,
    ): string {
        if ($targetLocale === "en" && !str_starts_with($url, "/en")) {
            return "/en" . $url;
        }
        if ($targetLocale === "fi" && str_starts_with($url, "/en")) {
            return substr($url, 3) ?: "/";
        }
        return $url;
    }

    /**
     * Safely gets the page ID as a string, handling proxy objects that may not have getId().
     *
     * @param PageInterface $page The page object
     * @return string The page ID as string or 'unknown' if not available
     */
    private function getPageIdSafely(PageInterface $page): string
    {
        try {
            $id = $page->getId();
            return $id !== null ? (string) $id : "unknown";
        } catch (\Throwable) {
            return "unknown";
        }
    }

    /**
     * Generate a localized URL based on a specified route and parameters.
     *
     * @param string $route The route name without locale suffix
     * @param string $targetLocale The target locale (e.g., 'en', 'fi')
     * @param array $parameters The route parameters
     * @return string The generated URL
     */
    public function getLocalizedRoute(
        string $route,
        string $targetLocale,
        array $parameters = [],
    ): string {
        // Strip any existing locale suffix if present
        $baseRoute = preg_replace('/\.(en|fi)$/', "", $route);
        $targetRoute = $baseRoute . "." . $targetLocale;

        try {
            // Generate URL with the provided parameters
            $url = $this->router->generate($targetRoute, $parameters);

            // For English locale, ensure /en prefix
            if ($targetLocale === "en" && !str_starts_with($url, "/en")) {
                return "/en" . $url;
            }

            // For Finnish locale, remove /en prefix if present
            if ($targetLocale === "fi" && str_starts_with($url, "/en")) {
                return substr($url, 3);
            }

            return $url;
        } catch (\Throwable) {
            // Fallback to root URL if route generation fails
            return $targetLocale === "en" ? "/en" : "/";
        }
    }
}
