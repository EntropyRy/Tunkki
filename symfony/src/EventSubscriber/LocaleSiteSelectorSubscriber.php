<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Sonata\PageBundle\Model\SiteManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LocaleSiteSelectorSubscriber implements EventSubscriberInterface
{
    private $siteManager;
    private $requestStack;

    public function __construct(
        SiteManagerInterface $siteManager,
        RequestStack $requestStack
    ) {
        $this->siteManager = $siteManager;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        // Must run before SonataPageBundle's site selector (priority 47)
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 49]
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Check if we're handling a locale change
        if ($request->getSession()->has('_desired_locale')) {
            $desiredLocale = $request->getSession()->get('_desired_locale');
            $request->getSession()->remove('_desired_locale'); // Clean up

            // Find the site for this locale
            $site = $this->siteManager->findOneBy([
                'locale' => $desiredLocale,
                'enabled' => true
            ]);

            if ($site) {
                // Force the site into the request attributes
                $request->attributes->set('_site', $site);
                $request->attributes->set('_locale', $desiredLocale);

                // Store in main request to ensure it persists through the request cycle
                $mainRequest = $this->requestStack->getMainRequest();
                if ($mainRequest) {
                    $mainRequest->attributes->set('_site', $site);
                    $mainRequest->attributes->set('_locale', $desiredLocale);
                }
            }
        }
    }
}
