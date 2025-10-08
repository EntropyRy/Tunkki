<?php

declare(strict_types=1);

/**
 * Debug script to check Sonata PageBundle CMS baseline seeding.
 *
 * Usage (from repo root):
 *   docker compose exec -T -e APP_ENV=test fpm php scripts/debug_baseline.php
 *
 * Behavior:
 *  - Loads vendor autoload and .env
 *  - Requires tests/bootstrap.php (which attempts to load CmsBaselineStory once)
 *  - Boots the Symfony Kernel (APP_ENV=test)
 *  - Verifies that exactly two Sites (fi, en) exist
 *  - Checks that each site has a root page "/" and key subpages
 *  - Prints a summary and exits with non-zero status if expectations are not met
 */

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

((function (): int {
    $start = microtime(true);

    $stdout = fopen('php://stdout', 'w');
    $stderr = fopen('php://stderr', 'w');
    $writeOut = static function (string $msg) use ($stdout): void {
        fwrite($stdout, $msg.(str_ends_with($msg, PHP_EOL) ? '' : PHP_EOL));
    };
    $writeErr = static function (string $msg) use ($stderr): void {
        fwrite($stderr, $msg.(str_ends_with($msg, PHP_EOL) ? '' : PHP_EOL));
    };

    $root = dirname(__DIR__);
    $autoload = $root.'/vendor/autoload.php';
    if (!is_file($autoload)) {
        $writeErr(
            '[debug-baseline] FATAL: vendor/autoload.php not found at '.
                $autoload,
        );

        return 1;
    }
    require $autoload;

    // Ensure APP_ENV is test for this diagnostic
    $_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'test';
    if (!isset($_SERVER['APP_DEBUG'])) {
        $_SERVER['APP_DEBUG'] = '0';
        $_ENV['APP_DEBUG'] = '0';
    }

    // Load .env for completeness (keeps parity with phpunit bootstrap)
    if (class_exists(Dotenv::class)) {
        try {
            new Dotenv()->bootEnv($root.'/.env');
        } catch (Throwable $e) {
            $writeErr(
                '[debug-baseline] WARNING: Dotenv boot failed: '.
                    $e->getMessage(),
            );
        }
    }

    // Load tests/bootstrap.php which will attempt to seed the baseline via CmsBaselineStory
    $testsBootstrap = $root.'/tests/bootstrap.php';
    if (!is_file($testsBootstrap)) {
        $writeErr(
            '[debug-baseline] FATAL: tests/bootstrap.php not found at '.
                $testsBootstrap,
        );

        return 1;
    }
    try {
        require $testsBootstrap;
    } catch (Throwable $e) {
        $writeErr(
            '[debug-baseline] WARNING: tests/bootstrap.php execution failed: '.
                $e->getMessage(),
        );
        // Continue; we can still attempt to boot kernel and inspect DB
    }

    // Boot Symfony kernel
    try {
        $kernel = new Kernel(
            $_SERVER['APP_ENV'] ?? 'test',
            (bool) ($_SERVER['APP_DEBUG'] ?? false),
        );
        $kernel->boot();
    } catch (Throwable $e) {
        $writeErr(
            '[debug-baseline] FATAL: Kernel boot failed: '.$e->getMessage(),
        );

        return 1;
    }

    // Fetch Doctrine EntityManager
    try {
        $container = $kernel->getContainer();
        /** @var Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        $em = $doctrine->getManager();
    } catch (Throwable $e) {
        $writeErr(
            '[debug-baseline] FATAL: Could not acquire Doctrine EntityManager: '.
                $e->getMessage(),
        );

        return 1;
    }

    $statusOk = true;
    $details = [];

    try {
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        $siteCount = method_exists($siteRepo, 'count')
            ? $siteRepo->count([])
            : count($siteRepo->findAll());
        $details[] = 'Sites: '.$siteCount;

        /** @var SonataPageSite[] $sites */
        $sites = method_exists($siteRepo, 'findAll')
            ? $siteRepo->findAll()
            : [];
        $locales = [];
        foreach ($sites as $s) {
            $loc = method_exists($s, 'getLocale')
                ? (string) $s->getLocale()
                : '';
            $locales[] = $loc;
        }
        sort($locales);
        $details[] = 'Locales: ['.implode(', ', $locales).']';

        if (2 !== $siteCount || $locales !== ['en', 'fi']) {
            $statusOk = false;
        }

        // Per-site checks: root "/" and key pages must exist
        $perSiteChecks = [];
        foreach ($sites as $site) {
            $loc = method_exists($site, 'getLocale')
                ? (string) $site->getLocale()
                : 'n/a';

            $root = method_exists($pageRepo, 'findOneBy')
                ? $pageRepo->findOneBy(['site' => $site, 'url' => '/'])
                : null;
            $rootOk = null !== $root;

            // Expected localized pages
            $expected = [
                'events' => 'en' === $loc ? '/events' : '/tapahtumat',
                'join' => 'en' === $loc ? '/join-us' : '/liity',
                'announcements' => 'en' === $loc ? '/announcements' : '/tiedotukset',
                'stream' => '/stream',
            ];
            $missing = [];
            foreach ($expected as $key => $url) {
                $page = method_exists($pageRepo, 'findOneBy')
                    ? $pageRepo->findOneBy(['site' => $site, 'url' => $url])
                    : null;
                if (null === $page) {
                    $missing[] = $url;
                }
            }

            $perSiteChecks[] = sprintf(
                'Site "%s": root=%s, missing=[%s]',
                $loc,
                $rootOk ? 'OK' : 'MISSING /',
                implode(', ', $missing),
            );

            if (!$rootOk || !empty($missing)) {
                $statusOk = false;
            }

            // Duplicate URL diagnostic (within site)
            $pages = method_exists($pageRepo, 'findBy')
                ? $pageRepo->findBy(['site' => $site])
                : [];
            $seen = [];
            $dupes = [];
            foreach ($pages as $p) {
                $url = method_exists($p, 'getUrl')
                    ? (string) $p->getUrl()
                    : null;
                if (!$url) {
                    continue;
                }
                if (!isset($seen[$url])) {
                    $seen[$url] = 1;
                } else {
                    ++$seen[$url];
                    $dupes[$url] = ($dupes[$url] ?? 1) + 1;
                }
            }
            if (!empty($dupes)) {
                $statusOk = false;
                $perSiteChecks[] = sprintf(
                    'Site "%s": duplicate URLs detected -> %s',
                    $loc,
                    json_encode($dupes, JSON_UNESCAPED_SLASHES),
                );
            }
        }

        // Output summary
        $writeOut(
            '[debug-baseline] ================================================',
        );
        $writeOut(
            '[debug-baseline] Env='.
                $_SERVER['APP_ENV'].
                ' PHP='.
                PHP_VERSION.
                ' time='.
                date('c'),
        );
        $writeOut(
            '[debug-baseline] Kernel='.
                $kernel::class.
                ' (debug='.
                (int) ($_SERVER['APP_DEBUG'] ?? 0).
                ')',
        );
        foreach ($details as $line) {
            $writeOut('[debug-baseline] '.$line);
        }
        foreach ($perSiteChecks as $line) {
            $writeOut('[debug-baseline] '.$line);
        }
        $writeOut(
            '[debug-baseline] ================================================',
        );

        if (!$statusOk) {
            $writeErr(
                '[debug-baseline] FAILURE: CMS baseline not in expected state (see details above).',
            );
        } else {
            $writeOut('[debug-baseline] OK: CMS baseline present and healthy.');
        }
    } catch (Throwable $e) {
        $writeErr(
            '[debug-baseline] FATAL: Unexpected error while checking baseline: '.
                $e->getMessage(),
        );
        $statusOk = false;
    } finally {
        // Close kernel to free resources
        try {
            $kernel->shutdown();
        } catch (Throwable) {
            // ignore
        }
    }

    $dur = sprintf('%.1fms', (microtime(true) - $start) * 1000);
    $writeOut('[debug-baseline] Duration: '.$dur);

    return $statusOk ? 0 : 2;
})()
    && exit(0))
    || exit(2);
