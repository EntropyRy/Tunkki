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
 * Adds debug + a conditional skip for POST /login so the security firewall
 * can authenticate before Sonata Page site resolution (which was causing 404s).
 */
final class SiteAwareKernelBrowser extends KernelBrowser
{
    protected function doRequest(object $request): Response
    {
        if ($request instanceof Request) {
            // Debug logging for POST /login BEFORE any wrapping
            if (
                strtoupper($request->getMethod()) === "POST" &&
                ($request->getPathInfo() === "/login" ||
                    // fallback if pathInfo not yet normalized
                    rtrim($request->getRequestUri(), "/") === "/login")
            ) {
                $keys = implode(",", array_keys($request->request->all()));
                fwrite(
                    \STDOUT,
                    "[TEST_DEBUG_LOGIN] RAW_POST pathInfo=" .
                        $request->getPathInfo() .
                        " requestUri=" .
                        $request->getRequestUri() .
                        " keys=" .
                        $keys .
                        " csrf=" .
                        ($request->request->has("_csrf_token") ? "yes" : "no") .
                        "\n",
                );
            }

            // Skip wrapping for POST /login (let security handle plain Request)
            if (
                strtoupper($request->getMethod()) === "POST" &&
                $this->isLoginPath($request)
            ) {
                return parent::doRequest($request);
            }

            if (!($request instanceof SiteRequest)) {
                $request = self::wrapAsSiteRequest($request);
            }
        }

        return parent::doRequest($request);
    }

    private function isLoginPath(Request $request): bool
    {
        $pi = $request->getPathInfo();
        if ($pi === "/login") {
            return true;
        }
        $uri = $request->getRequestUri();
        if ($uri === "/login" || $uri === "/login/") {
            return true;
        }
        return false;
    }

    private static function wrapAsSiteRequest(Request $request): SiteRequest
    {
        $raw = $request->getRequestUri() ?: "/";
        if ($raw[0] !== "/") {
            $raw = "/" . ltrim($raw, "/");
        }
        while (str_starts_with($raw, "//")) {
            $raw = "/" . ltrim($raw, "/");
        }

        $server = $request->server->all();
        $server["HTTP_HOST"] = "localhost";
        $server["SERVER_NAME"] = "localhost";
        if (!isset($server["SERVER_PORT"])) {
            $server["SERVER_PORT"] = 80;
        }
        $server["REQUEST_URI"] = $raw;

        $absolute = "http://localhost" . $raw;

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
        if ($locale !== null && $locale !== "") {
            $siteRequest->setLocale($locale);
        }

        $siteRequest->setBaseUrl($request->getBaseUrl());
        $siteRequest->setPathInfo(parse_url($raw, PHP_URL_PATH) ?: "/");

        return $siteRequest;
    }
}
