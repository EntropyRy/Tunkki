<?php

declare(strict_types=1);

namespace App\Site;

use Sonata\PageBundle\CmsManager\DecoratorStrategyInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Sonata\PageBundle\Model\SiteManagerInterface;
use Sonata\PageBundle\Site\HostPathSiteSelector;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Sonata\PageBundle\Request\SiteRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Custom site selector that supports Symfony's localized routing.
 * Blocks raw localized routes and validates site-locale compatibility.
 */
final class CustomHostPathByLocaleSiteSelector extends HostPathSiteSelector
{
    public function __construct(
        protected SiteManagerInterface $siteManager,
        protected DecoratorStrategyInterface $decoratorStrategy,
        protected SeoPageInterface $seoPage,
        protected RouterInterface $router
    ) {
        parent::__construct($siteManager, $decoratorStrategy, $seoPage);
    }
    #[\Override]
    public function handleKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request instanceof SiteRequestInterface) {
            throw new \RuntimeException(
                'You must configure runtime on your composer.json in order to use "Host path by locale" strategy, take a look on page bundle multiside doc.'
            );
        }

        $currentPath = $request->getPathInfo();

        // First, check if this is a raw localized route that should be blocked
        if ($this->isRawLocalizedRoute($currentPath)) {
            // Don't set any site - this will cause 404 for raw localized routes
            return;
        }

        $enabledSites = [];
        $pathInfo = null;

        foreach ($this->getSites($request) as $site) {
            if (!$site->isEnabled()) {
                continue;
            }

            $enabledSites[] = $site;

            $match = $this->matchRequest($site, $request);

            if (false === $match) {
                continue;
            }

            if (!$this->shouldSiteHandlePath($match, $site)) {
                continue;
            }

            $this->site = $site;
            $pathInfo = $match;

            if (!$this->site->isLocalhost()) {
                break;
            }
        }

        if ($this->site instanceof SiteInterface) {
            $request->setPathInfo($pathInfo ?? "/");
        }

        // no valid site, but try to find a default site for the current request
        if (
            !$this->site instanceof SiteInterface &&
            \count($enabledSites) > 0
        ) {
            $defaultSite = $this->getPreferredSite($enabledSites, $request);
            \assert($defaultSite instanceof SiteInterface);

            $url = $defaultSite->getUrl();
            \assert(null !== $url);

            $event->setResponse(new RedirectResponse($url ? $url : "/"));
        } elseif (
            $this->site instanceof SiteInterface &&
            null !== $this->site->getLocale()
        ) {
            $request->attributes->set("_locale", $this->site->getLocale());
        }
    }

    /**
     * Check if this is a raw localized route that should not be directly accessible.
     */
    private function isRawLocalizedRoute(string $path): bool
    {
        try {
            $routeInfo = $this->router->match($path);
            $routeName = $routeInfo["_route"] ?? "";

            // If this route has a locale suffix (.fi or .en), it's a raw localized route
            if (preg_match('/\.(fi|en)$/', $routeName)) {
                return true;
            }
        } catch (\Throwable) {
            // Router couldn't match this path - not a localized route
        }

        return false;
    }

    /**
     * Check if a site should handle a specific path.
     */
    private function shouldSiteHandlePath(
        string $matchedPath,
        SiteInterface $site
    ): bool {
        $siteLocale = $site->getLocale();

        // Try to match the stripped path to see if it's a localized route
        try {
            $routeInfo = $this->router->match($matchedPath);
            $routeName = $routeInfo["_route"] ?? "";
            $routeLocale = $routeInfo["_locale"] ?? null;

            // If this is a localized route, ensure the locale matches the site
            if ($routeLocale && $routeLocale !== $siteLocale) {
                return false;
            }
        } catch (\Throwable) {
            // Not a recognized route - let Sonata handle it normally
        }

        return true;
    }
}
