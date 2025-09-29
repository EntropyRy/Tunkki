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

/**
 * Custom site selector stripped of legacy in-selector canonical redirect logic.
 * Canonical locale enforcement is now handled solely by the heuristic subscriber.
 * This class only preserves the original HostPathSiteSelector site resolution.
 */
final class CustomHostPathByLocaleSiteSelector extends HostPathSiteSelector
{
    public function __construct(
        protected SiteManagerInterface $siteManager,
        protected DecoratorStrategyInterface $decoratorStrategy,
        protected SeoPageInterface $seoPage,
    ) {
        parent::__construct($siteManager, $decoratorStrategy, $seoPage);
    }

    // NOTE: Removed local onKernelRequest / onKernelRequestRedirect proxy methods.
    // The base class defines final implementations; our earlier override attempt caused a fatal error.
    // All logic now relies solely on handleKernelRequest(), which the parent wiring should invoke.

    #[\Override]
    public function handleKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request instanceof SiteRequestInterface) {
            throw new \RuntimeException(
                'You must configure runtime on your composer.json in order to use "Host path by locale" strategy.',
            );
        }

        // Canonical prefix logic removed – handled externally by heuristic subscriber.

        // Original HostPathSiteSelector logic (unchanged)
        $enabledSites = [];
        $pathInfo = null;

        foreach ($this->getSites($request) as $site) {
            if (!$site->isEnabled()) {
                continue;
            }

            $enabledSites[] = $site;
            $match = $this->matchRequest($site, $request);

            if ($match === false) {
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

        if (
            !$this->site instanceof SiteInterface &&
            \count($enabledSites) > 0
        ) {
            $defaultSite = $this->getPreferredSite($enabledSites, $request);
            \assert($defaultSite instanceof SiteInterface);
            $url = $defaultSite->getUrl();
            \assert(null !== $url);
            $event->setResponse(new RedirectResponse($url ?: "/"));
            return;
        }

        if (
            $this->site instanceof SiteInterface &&
            null !== $this->site->getLocale()
        ) {
            $request->attributes->set("_locale", $this->site->getLocale());
        }

        // (Removed fallback second-pass canonical enforcement block; heuristic + early pass now sufficient)
    }
}
