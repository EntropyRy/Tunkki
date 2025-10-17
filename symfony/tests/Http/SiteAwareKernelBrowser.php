<?php

declare(strict_types=1);

namespace App\Tests\Http;

use Sonata\PageBundle\Request\SiteRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Test-only KernelBrowser that wraps outgoing requests into Sonata's SiteRequest.
 *
 * Minimal version (debug logging removed).
 */
final class SiteAwareKernelBrowser extends KernelBrowser
{
    /**
     * Holds the last DomCrawler instance returned by request().
     * Allows tests (or higher-level helpers) to fetch the crawler even when
     * intermediate code only has a reference to the browser.
     */
    private ?\Symfony\Component\DomCrawler\Crawler $lastCrawler = null;

    /**
     * Override to capture and store the crawler for later retrieval.
     */
    public function request(
        string $method,
        string $uri,
        array $parameters = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
        bool $changeHistory = true,
    ): \Symfony\Component\DomCrawler\Crawler {
        $crawler = parent::request(
            $method,
            $uri,
            $parameters,
            $files,
            $server,
            $content,
            $changeHistory,
        );
        $this->lastCrawler = $crawler;

        return $crawler;
    }

    /**
     * Accessor for the most recent crawler (null if no request performed yet).
     */
    public function getLastCrawler(): ?\Symfony\Component\DomCrawler\Crawler
    {
        return $this->lastCrawler;
    }

    /**
     * Assert that a selector exists in the last response.
     * Convenience method for Sonata multisite tests using SiteRequest.
     */
    public function assertSelectorExists(string $selector, string $message = ''): void
    {
        if (null === $this->lastCrawler) {
            throw new \LogicException('No crawler available. Did you make a request?');
        }

        $count = $this->lastCrawler->filter($selector)->count();
        if (0 === $count) {
            throw new \PHPUnit\Framework\AssertionFailedError($message ?: "Selector '{$selector}' not found.");
        }
    }

    /**
     * Assert that a selector contains specific text.
     */
    public function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        if (null === $this->lastCrawler) {
            throw new \LogicException('No crawler available. Did you make a request?');
        }

        $node = $this->lastCrawler->filter($selector);
        if (0 === $node->count()) {
            throw new \PHPUnit\Framework\AssertionFailedError("Selector '{$selector}' not found.");
        }

        $actualText = $node->text();
        if (!str_contains($actualText, $text)) {
            throw new \PHPUnit\Framework\AssertionFailedError($message ?: "Text '{$text}' not found in selector '{$selector}'. Actual: '{$actualText}'");
        }
    }

    public function loginUser(
        object $user,
        string $firewallContext = 'main',
        array $tokenAttributes = [],
    ): static {
        $ret = parent::loginUser($user, $firewallContext, $tokenAttributes);

        try {
            $container = $this->getContainer();

            if ($container->has('session')) {
                $session = $container->get('session');
                if (
                    method_exists($session, 'isStarted')
                    && !$session->isStarted()
                ) {
                    $session->start();
                }

                if ($container->has('security.token_storage')) {
                    $ts = $container->get('security.token_storage');
                    $token = method_exists($ts, 'getToken')
                        ? $ts->getToken()
                        : null;
                    if ($token) {
                        $session->set(
                            '_security_'.$firewallContext,
                            serialize($token),
                        );
                        $session->save();
                    }
                }

                $this->getCookieJar()->set(
                    new Cookie(
                        $session->getName(),
                        $session->getId(),
                        null,
                        '/',
                        'localhost',
                    ),
                );
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[SiteAwareKernelBrowser::loginUser] session/token persist failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }

        return $ret;
    }

    protected function doRequest(object $request): Response
    {
        $debug = (bool) getenv('TEST_USER_CREATION_DEBUG');

        $preTokenInfo = null;
        $preSessionId = null;
        $container = $this->getContainer();

        if ($debug) {
            try {
                if ($container->has('security.token_storage')) {
                    $ts = $container->get('security.token_storage');
                    if (method_exists($ts, 'getToken')) {
                        $token = $ts->getToken();
                        if ($token) {
                            $user = $token->getUser();
                            $preTokenInfo = [
                                'tokenClass' => get_debug_type($token),
                                'userType' => \is_object($user)
                                    ? $user::class
                                    : get_debug_type($user),
                                'roles' => method_exists($token, 'getRoleNames')
                                    ? $token->getRoleNames()
                                    : (method_exists($token, 'getRoles')
                                        ? $token->getRoles()
                                        : []),
                            ];
                        }
                    }
                }
                if ($request instanceof Request && $request->hasSession()) {
                    $preSessionId = $request->getSession()->getId();
                }
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] pre-request diag failed: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
        }

        if ($request instanceof Request && !($request instanceof SiteRequest)) {
            // Persist security token to session BEFORE wrapping so the security layer
            // can read it during kernel.request (ContextListener equivalent).
            try {
                if (
                    $container->has('session')
                    && $container->has('security.token_storage')
                ) {
                    $session = $container->get('session');
                    $tokenStorage = $container->get('security.token_storage');
                    $token = $tokenStorage->getToken();
                    // Always ensure session is started and a session cookie is present on the request
                    if (
                        method_exists($session, 'isStarted')
                        && !$session->isStarted()
                    ) {
                        $session->start();
                    }
                    // Ensure BrowserKit sends the session cookie on this very request
                    $this->getCookieJar()->set(
                        new Cookie(
                            $session->getName(),
                            $session->getId(),
                            null,
                            '/',
                            'localhost',
                        ),
                    );
                    // Also inject cookie into the pending Request object before wrap
                    $request->cookies->set(
                        $session->getName(),
                        $session->getId(),
                    );

                    // If a token exists, persist it into the session for the main firewall
                    if ($token) {
                        $firewall = 'main';
                        $sessionKey = '_security_'.$firewall;
                        if (!$session->has($sessionKey)) {
                            $session->set($sessionKey, serialize($token));
                            $session->save();
                        }
                    }
                }
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] pre-wrap token persist failed: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
            $request = $this->wrapAsSiteRequest($request, $container);
        }

        // Hydrate token from session into token storage if missing (simulate ContextListener behavior)
        try {
            if (
                $container->has('security.token_storage')
                && $container->has('session')
            ) {
                $ts = $container->get('security.token_storage');
                $existingToken = method_exists($ts, 'getToken')
                    ? $ts->getToken()
                    : null;
                if (!$existingToken) {
                    $session = $container->get('session');
                    $firewall = 'main';
                    $sessionKey = '_security_'.$firewall;
                    if (
                        method_exists($session, 'has')
                        && $session->has($sessionKey)
                    ) {
                        $serialized = $session->get($sessionKey);
                        if (\is_string($serialized) && '' !== $serialized) {
                            $un = @unserialize($serialized);
                            if (
                                $un instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface
                            ) {
                                if (method_exists($ts, 'setToken')) {
                                    $ts->setToken($un);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[SiteAwareKernelBrowser] token hydration from session failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }

        $response = parent::doRequest($request);

        if ($debug) {
            try {
                $postTokenInfo = null;
                $postSessionId = null;
                if ($container->has('security.token_storage')) {
                    $ts = $container->get('security.token_storage');
                    if (method_exists($ts, 'getToken')) {
                        $token = $ts->getToken();
                        if ($token) {
                            $user = $token->getUser();
                            $postTokenInfo = [
                                'tokenClass' => get_debug_type($token),
                                'userType' => \is_object($user)
                                    ? $user::class
                                    : get_debug_type($user),
                                'roles' => method_exists($token, 'getRoleNames')
                                    ? $token->getRoleNames()
                                    : (method_exists($token, 'getRoles')
                                        ? $token->getRoles()
                                        : []),
                            ];
                        }
                    }
                }
                if ($request instanceof Request && $request->hasSession()) {
                    $postSessionId = $request->getSession()->getId();
                }

                $controller = null;
                if ($request instanceof Request) {
                    $controller = $request->attributes->get('_controller');
                }

                $payload = [
                    'ts' => microtime(true),
                    'browser' => 'SiteAwareKernelBrowser',
                    'path' => $request instanceof Request
                            ? $request->getRequestUri()
                            : null,
                    'method' => $request instanceof Request
                            ? $request->getMethod()
                            : null,
                    'status' => $response->getStatusCode(),
                    'controller' => $controller,
                    'baseUrl' => $request instanceof Request
                            ? $request->getBaseUrl()
                            : null,
                    'pathInfo' => $request instanceof Request
                            ? $request->getPathInfo()
                            : null,
                    'session' => [
                        'pre' => $preSessionId,
                        'post' => $postSessionId,
                        'changed' => $preSessionId !== $postSessionId,
                    ],
                    'token' => [
                        'pre' => $preTokenInfo,
                        'post' => $postTokenInfo,
                    ],
                ];
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] '.
                        json_encode($payload, \JSON_UNESCAPED_SLASHES).
                        \PHP_EOL,
                );
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] post-request diag failed: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
        }

        return $response;
    }

    private function wrapAsSiteRequest(
        Request $request,
        $container,
    ): SiteRequest {
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

        // Simplified session preservation: always reuse container session if available.
        if ($container->has('session')) {
            try {
                /** @var SessionInterface $session */
                $session = $container->get('session');
                if (
                    !method_exists($session, 'isStarted')
                    || !$session->isStarted()
                ) {
                    $session->start();
                }
                $siteRequest->setSession($session);
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] session reuse failed: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
        } elseif ($request->hasSession()) {
            // Fallback: keep original request session
            $siteRequest->setSession($request->getSession());
        }

        // Set locale based on path prefix ('/en' => 'en', otherwise 'fi') so HostPathByLocaleSiteSelector starts with consistent request locale.
        $pathForLocale = parse_url($raw, \PHP_URL_PATH) ?: '/';
        if (
            '/en' === $pathForLocale
            || str_starts_with($pathForLocale, '/en/')
        ) {
            $siteRequest->setDefaultLocale('en');
            $siteRequest->setLocale('en');
            $siteRequest->attributes->set('_locale', 'en');
            if (getenv('TEST_USER_CREATION_DEBUG')) {
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] chosen_locale=en raw='.
                        $raw.
                        \PHP_EOL,
                );
            }
        } else {
            $siteRequest->setDefaultLocale('fi');
            $siteRequest->setLocale('fi');
            $siteRequest->attributes->set('_locale', 'fi');
            if (getenv('TEST_USER_CREATION_DEBUG')) {
                @fwrite(
                    \STDERR,
                    '[SiteAwareKernelBrowser] chosen_locale=fi raw='.
                        $raw.
                        \PHP_EOL,
                );
            }
        }

        // Normalize path handling for host-with-path-by-locale:
        // For English, set baseUrl '/en' and strip the '/en' prefix from pathInfo.
        $path = parse_url($raw, \PHP_URL_PATH) ?: '/';

        // Default to Finnish site (no prefix)
        $baseUrl = '';
        $pathInfo = $path;

        if ('/en' === $path || str_starts_with($path, '/en/')) {
            // English site: baseUrl '/en' with pathInfo stripped of the prefix
            $baseUrl = '/en';
            $rest = substr($path, 3); // remove '/en'
            if ('' === $rest || false === $rest) {
                $pathInfo = '/';
            } else {
                $pathInfo = '/'.ltrim((string) $rest, '/');
                if ('' === $pathInfo) {
                    $pathInfo = '/';
                }
            }
        }

        // Do not override baseUrl/pathInfo here. Let the router compute them.

        return $siteRequest;
    }
}
