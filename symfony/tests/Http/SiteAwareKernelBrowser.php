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
 * Minimal version (debug logging removed).
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

    private static function wrapAsSiteRequest(Request $request): SiteRequest
    {
        $raw = $request->getRequestUri() ?: '/';
        if ('/' !== $raw[0]) {
            $raw = '/'.ltrim($raw, '/');
        }
        while (str_starts_with($raw, '//')) {
            $raw = '/'.ltrim($raw, '/');
        }

        $server = $request->server->all();
        $server['HTTP_HOST'] = 'localhost';
        $server['SERVER_NAME'] = 'localhost';
        if (!isset($server['SERVER_PORT'])) {
            $server['SERVER_PORT'] = 80;
        }
        $server['REQUEST_URI'] = $raw;

        $absolute = 'http://localhost'.$raw;

        $siteRequest = SiteRequest::create(
            $absolute,
            $request->getMethod(),
            $request->request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );

        $siteRequest->attributes->add($request->attributes->all());

        if ($request->hasSession()) {
            $siteRequest->setSession($request->getSession());
        }

        $locale = $request->getLocale();
        if (null !== $locale && '' !== $locale) {
            $siteRequest->setLocale($locale);
        }

        $siteRequest->setBaseUrl($request->getBaseUrl());
        $siteRequest->setPathInfo(parse_url($raw, PHP_URL_PATH) ?: '/');

        return $siteRequest;
    }
}
