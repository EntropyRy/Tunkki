<?php

declare(strict_types=1);

namespace App\Tests\Http;

use Sonata\PageBundle\Request\SiteRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-only KernelBrowser that wraps outgoing requests into Sonata's SiteRequest.
 *
 * This mimics Sonata's runtime behavior so Site selectors that require
 * SiteRequestInterface can operate during BrowserKit-based functional tests.
 *
 * Usage:
 *   - In your tests, instantiate this browser instead of the default:
 *       $client = new \App\Tests\Http\SiteAwareKernelBrowser(static::createKernel());
 *     Or extend WebTestCase and override createClient() to return this browser.
 */
final class SiteAwareKernelBrowser extends KernelBrowser
{
    protected function doRequest(object $request): Response
    {
        if ($request instanceof Request && !($request instanceof SiteRequest)) {
            $request = self::wrapAsSiteRequest($request);
        }

        return parent::doRequest($request);
    }

    /**
     * Convert a standard Symfony Request into a Sonata SiteRequest while
     * preserving all relevant request data.
     */
    private static function wrapAsSiteRequest(Request $request): SiteRequest
    {
        // Create a SiteRequest using the original request parameters
        $siteRequest = SiteRequest::create(
            $request->getUri(),
            $request->getMethod(),
            $request->request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent(),
        );

        // Preserve route attributes and any other attributes set upstream
        $siteRequest->attributes->add($request->attributes->all());

        // Preserve session if one is attached
        if ($request->hasSession()) {
            $siteRequest->setSession($request->getSession());
        }

        // Ensure base URL and path info are carried over exactly
        $siteRequest->setBaseUrl($request->getBaseUrl());
        $siteRequest->setPathInfo($request->getPathInfo());

        // Preserve locale if set
        $locale = $request->getLocale();
        if (null !== $locale && $locale !== "") {
            $siteRequest->setLocale($locale);
        }

        return $siteRequest;
    }
}
