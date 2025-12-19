<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use Doctrine\ORM\EntityManagerInterface;
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
 * 1. Technical alias transformation (primary method) - Fast pattern-based alias matching
 * 2. Menu-based lookup (fallback method) - Uses Menu entity relationships
 * 3. Router generation for Symfony routes - Swaps locale suffix in route name
 */
class LocalizedUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsManagerSelectorInterface $cmsManagerSelector,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('localized_url', $this->getLocalizedUrl(...)),
        ];
    }

    /**
     * Generates a localized URL for the current page or a specific page.
     *
     * Usage in templates:
     * - For Sonata pages: {{ localized_url('en', page.id|default(null)) }}
     * - For Symfony routes: {{ localized_url('en') }} (router handles locale automatically)
     *
     * This method uses multiple strategies to find the localized version:
     * 1. If a page object/ID is provided, uses technical alias lookup first (simpler)
     * 2. Falls back to Menu-based lookup (always works)
     * 3. For regular Symfony routes, lets the router handle locale prefixing
     * 4. Final fallback returns root path with appropriate locale prefix
     *
     * @param string                 $targetLocale The target locale ('fi' or 'en')
     * @param PageInterface|int|null $page         Page object or ID. Required for Sonata CMS pages.
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

        // Handle root path
        $currentPath = $request->getPathInfo();
        if ('/' === $currentPath || '/en' === $currentPath) {
            return 'en' === $targetLocale ? '/en' : '/';
        }

        // For regular Symfony routes, swap the locale suffix
        $currentRoute = $request->attributes->get('_route');
        if ($currentRoute && 'page_slug' !== $currentRoute) {
            $routeParams = $request->attributes->get('_route_params', []);

            return $this->router->generate($currentRoute.'.'.$targetLocale, $routeParams);
        }

        // Final fallback for unknown routes
        return 'en' === $targetLocale ? '/en' : '/';
    }

    /**
     * Generates a localized URL from a specific Sonata Page object.
     *
     * Strategy (optimized for simplicity):
     * 1. Try technical alias first (simpler, faster when available)
     * 2. Fallback to Menu lookup (always works, but more complex)
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
        // First try technical alias (simpler when available)
        $pageAlias = $page->getPageAlias();
        $site = $page->getSite();

        // Use the site's locale to build target alias (no regex needed!)
        if ($pageAlias && $site) {
            $sourceLocale = $site->getLocale();
            $localeSuffix = '_'.$sourceLocale;

            // Check if alias ends with locale suffix (e.g., _page_alias_services_fi)
            if (str_ends_with($pageAlias, $localeSuffix)) {
                $baseAlias = substr($pageAlias, 0, -\strlen($localeSuffix));
                $targetAlias = $baseAlias.'_'.$targetLocale;
                $targetPage = $this->findPageByAlias($targetAlias);

                if ($targetPage && $targetPage->getEnabled()) {
                    $url = $targetPage->getUrl();
                    $site = $targetPage->getSite();
                    if ($url && $site) {
                        // Site's relativePath already contains the locale prefix (/en or '')
                        return ($site->getRelativePath() ?? '').$url;
                    }
                }
            }
        }

        // Fallback to Menu-based lookup (always works)
        $targetPage = $this->findPageThroughMenu($page, $targetLocale);
        if ($targetPage && $targetPage->getEnabled()) {
            $url = $targetPage->getUrl();
            $site = $targetPage->getSite();
            if ($url && $site) {
                // Site's relativePath already contains the locale prefix (/en or '')
                return ($site->getRelativePath() ?? '').$url;
            }
        }

        // Final fallback
        return 'en' === $targetLocale ? '/en' : '/';
    }

    /**
     * Finds a Sonata Page by its technical alias using CmsManager.
     *
     * @param string $alias The technical alias to search for (e.g., '_page_alias_services_fi')
     *
     * @return PageInterface|null The found page or null if not found
     */
    private function findPageByAlias(string $alias): ?PageInterface
    {
        try {
            $request = $this->requestStack->getCurrentRequest();
            if (!$request instanceof Request) {
                return null;
            }

            $site = $request->attributes->get('site');
            if (!$site instanceof SiteInterface) {
                return null;
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
        } catch (\Throwable) {
            // Ignore and fallback to ID-based resolution
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
                    return $menuItem->getPageByLang($targetLocale);
                }
            } catch (\Throwable) {
            }
        }

        return null;
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
        $id = $page->getId();

        return null !== $id ? (string) $id : 'unknown';
    }
}
