<?php

declare(strict_types=1);

/**
 * Route debug helper for Sonata PageBundle + CMF ChainRouter.
 *
 * Simulates a SiteRequest and matches via the ChainRouter for "/" and "/en/".
 *
 * Usage (from repo root):
 *   docker compose exec -T -e APP_ENV=test fpm php scripts/route_debug.php
 *
 * Notes:
 * - This script boots the Symfony kernel under APP_ENV=test, requires tests/bootstrap.php
 *   (so the Foundry CmsBaselineStory can seed Sites/Pages once), and then builds
 *   Sonata\PageBundle\Request\SiteRequest instances to match against the router chain.
 * - It will print which inner router matched, the resolved route/_controller, and
 *   the baseUrl/pathInfo used for matching.
 */

use App\Kernel;
use Sonata\PageBundle\Request\SiteRequest;
use Symfony\Cmf\Component\Routing\ChainRouter;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

((function (): int {
    $stdout = fopen('php://stdout', 'w');
    $stderr = fopen('php://stderr', 'w');
    $out = static function (string $msg) use ($stdout): void {
        fwrite($stdout, $msg.(str_ends_with($msg, PHP_EOL) ? '' : PHP_EOL));
    };
    $err = static function (string $msg) use ($stderr): void {
        fwrite($stderr, $msg.(str_ends_with($msg, PHP_EOL) ? '' : PHP_EOL));
    };

    $root = dirname(__DIR__);

    // Autoload
    $autoload = $root.'/vendor/autoload.php';
    if (!is_file($autoload)) {
        $err('[route-debug] FATAL: Missing vendor autoload at '.$autoload);

        return 1;
    }
    require $autoload;

    // Ensure test env by default
    $_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'test';
    $_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '0';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '0';

    // Load .env (parity with PHPUnit bootstrap)
    if (class_exists(Dotenv::class)) {
        try {
            new Dotenv()->bootEnv($root.'/.env');
        } catch (Throwable $e) {
            $err(
                '[route-debug] WARNING: Dotenv boot failed: '.
                    $e->getMessage(),
            );
        }
    }

    // Load test bootstrap (loads CmsBaselineStory once; non-fatal on failure)
    $testsBootstrap = $root.'/tests/bootstrap.php';
    if (is_file($testsBootstrap)) {
        try {
            require $testsBootstrap;
        } catch (Throwable $e) {
            $err(
                '[route-debug] WARNING: tests/bootstrap.php failed: '.
                    $e->getMessage(),
            );
        }
    } else {
        $err(
            '[route-debug] WARNING: tests/bootstrap.php not found at '.
                $testsBootstrap,
        );
    }

    // Boot Kernel
    try {
        $kernel = new Kernel(
            $_SERVER['APP_ENV'] ?? 'test',
            (bool) ($_SERVER['APP_DEBUG'] ?? false),
        );
        $kernel->boot();
    } catch (Throwable $e) {
        $err('[route-debug] FATAL: Kernel boot failed: '.$e->getMessage());

        return 2;
    }

    $container = $kernel->getContainer();

    // Fetch router (ChainRouter alias)
    try {
        /** @var ChainRouter $router */
        $router = $container->get('router');
        if (!$router instanceof ChainRouter) {
            $err(
                '[route-debug] FATAL: Router is not a ChainRouter (got '.
                    get_class($router).
                    ').',
            );

            return 3;
        }
    } catch (Throwable $e) {
        $err(
            '[route-debug] FATAL: Unable to obtain router: '.$e->getMessage(),
        );

        return 3;
    }

    // Describe chain routers
    $chain = [];
    try {
        $ref = new ReflectionObject($router);
        $prop = $ref->getProperty('routers');
        $prop->setAccessible(true);
        /** @var array<int,array<object>> $routers */
        $routers = $prop->getValue($router);
        ksort($routers);
        foreach ($routers as $prio => $list) {
            foreach ($list as $r) {
                $chain[] = ['priority' => $prio, 'router' => get_class($r)];
            }
        }
    } catch (Throwable $e) {
        $err(
            '[route-debug] WARNING: Could not introspect ChainRouter: '.
                $e->getMessage(),
        );
    }

    $out('[route-debug] =====================================================');
    $out(
        '[route-debug] Env='.
            $_SERVER['APP_ENV'].
            ' PHP='.
            PHP_VERSION.
            ' time='.
            date('c'),
    );
    $out('[route-debug] ChainRouters (priority => class):');
    foreach ($chain as $row) {
        $out(
            sprintf(
                '[route-debug]   %3d => %s',
                (int) $row['priority'],
                (string) $row['router'],
            ),
        );
    }

    // Optionally provide session to the request
    $getSession = function () use (
        $container,
    ): ?\Symfony\Component\HttpFoundation\Session\SessionInterface {
        try {
            return $container->has('session')
                ? $container->get('session')
                : null;
        } catch (Throwable) {
            return null;
        }
    };

    // Build a SiteRequest for a given path, computing baseUrl/pathInfo
    $makeSiteRequest = function (string $rawPath) use (
        $getSession,
    ): SiteRequest {
        $path = $rawPath;
        if ('' === $path || '/' !== $path[0]) {
            $path = '/'.ltrim($path, '/');
        }
        while (str_starts_with($path, '//')) {
            $path = '/'.ltrim($path, '/');
        }

        $absolute = 'http://localhost'.$path;
        $server = [
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'REQUEST_URI' => $path,
        ];

        $req = SiteRequest::create($absolute, 'GET', [], [], [], $server, null);

        // Locale based on prefix: default fi, '/en' => en
        $onlyPath = parse_url($path, PHP_URL_PATH) ?: '/';
        if ('/en' === $onlyPath || str_starts_with($onlyPath, '/en/')) {
            $req->setDefaultLocale('en');
            $req->setLocale('en');
            $req->attributes->set('_locale', 'en');
        } else {
            $req->setDefaultLocale('fi');
            $req->setLocale('fi');
            $req->attributes->set('_locale', 'fi');
        }

        // Compute baseUrl/pathInfo like our SiteAwareKernelBrowser
        $baseUrl = '';
        $pathInfo = $onlyPath;

        if ('/en' === $onlyPath || str_starts_with($onlyPath, '/en/')) {
            $baseUrl = '/en';
            $rest = substr($onlyPath, 3); // remove '/en'
            if ('' === $rest || false === $rest) {
                $pathInfo = '/';
            } else {
                $pathInfo = '/'.ltrim((string) $rest, '/');
                if ('' === $pathInfo) {
                    $pathInfo = '/';
                }
            }
        }

        // Attach container session if available
        $session = $getSession();
        if (
            $session instanceof Symfony\Component\HttpFoundation\Session\SessionInterface
        ) {
            if (
                !method_exists($session, 'isStarted')
                || !$session->isStarted()
            ) {
                $session->start();
            }
            $req->setSession($session);
        }

        // Apply computed baseUrl/pathInfo (public methods on Request in Symfony 7)
        if (method_exists($req, 'setBaseUrl')) {
            $req->setBaseUrl($baseUrl);
        }
        if (method_exists($req, 'setPathInfo')) {
            $req->setPathInfo($pathInfo);
        }

        return $req;
    };

    // Try a match through the inner routers to learn which one succeeded
    $tryMatchThroughChain = function (Request $req) use ($router): array {
        $result = [
            'matched' => false,
            'routerClass' => null,
            'route' => null,
            'attributes' => null,
            'error' => null,
        ];

        try {
            $ref = new ReflectionObject($router);
            $prop = $ref->getProperty('routers');
            $prop->setAccessible(true);
            /** @var array<int,array<object>> $routers */
            $routers = $prop->getValue($router);
            ksort($routers);

            foreach ($routers as $list) {
                foreach ($list as $inner) {
                    try {
                        if ($inner instanceof RequestMatcherInterface) {
                            $attributes = $inner->matchRequest($req);
                        } else {
                            // Fallback to path-only
                            $attributes = $inner->match($req->getPathInfo());
                        }
                        // On first success:
                        $result['matched'] = true;
                        $result['routerClass'] = get_class($inner);
                        $result['route'] = $attributes['_route'] ?? null;
                        $result['attributes'] = $attributes;

                        return $result;
                    } catch (ResourceNotFoundException $e) {
                        // Try next
                        continue;
                    } catch (Throwable $e) {
                        // Record the last error and keep trying
                        $result['error'] = $e->getMessage();
                        continue;
                    }
                }
            }

            // If none matched, ChainRouter would 404 as well
            $result['matched'] = false;
            $result['error'] = $result['error'] ?? 'No inner router matched';
        } catch (Throwable $e) {
            $result['matched'] = false;
            $result['error'] =
                'Chain introspection failed: '.$e->getMessage();
        }

        return $result;
    };

    $paths = ['/', '/en/'];

    foreach ($paths as $path) {
        $req = $makeSiteRequest($path);

        $out(
            '[route-debug] -----------------------------------------------------',
        );
        $out('[route-debug] Path: '.$path);
        $out('[route-debug] Request URI: '.$req->getUri());
        $out('[route-debug] Locale: '.$req->getLocale());
        $out(
            '[route-debug] baseUrl='.
                $req->getBaseUrl().
                ' pathInfo='.
                $req->getPathInfo(),
        );

        // Try matching via ChainRouter directly (matchRequest preferred)
        $matchInfo = [
            'matched' => false,
            'route' => null,
            'controller' => null,
            'attributes' => null,
            'error' => null,
        ];

        try {
            if ($router instanceof RequestMatcherInterface) {
                $attrs = $router->matchRequest($req);
            } else {
                $attrs = $router->match($req->getPathInfo());
            }
            $matchInfo['matched'] = true;
            $matchInfo['route'] = $attrs['_route'] ?? null;
            $matchInfo['controller'] = $attrs['_controller'] ?? null;
            $matchInfo['attributes'] = $attrs;
        } catch (Throwable $e) {
            $matchInfo['matched'] = false;
            $matchInfo['error'] = $e->getMessage();
        }

        // Try to identify which inner router matched
        $inner = $tryMatchThroughChain($req);

        $out(
            '[route-debug] ChainRouter result: '.
                json_encode($matchInfo, JSON_UNESCAPED_SLASHES),
        );
        $out(
            '[route-debug] Inner router probe: '.
                json_encode($inner, JSON_UNESCAPED_SLASHES),
        );
    }

    $out('[route-debug] =====================================================');

    try {
        $kernel->shutdown();
    } catch (Throwable) {
        // ignore
    }

    return 0;
})()
    && exit(0))
    || exit(0);
