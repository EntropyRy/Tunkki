<?php

declare(strict_types=1);

/**
 * Force-load the immutable Sonata CMS baseline (CmsBaselineStory) and print site stats.
 *
 * Usage (from repo root):
 *   docker compose exec -T -e APP_ENV=test fpm php scripts/seed_baseline_once.php
 *
 * Notes:
 * - Intended for APP_ENV=test. If APP_ENV is not set, this script defaults to "test".
 * - Idempotent: running multiple times should not create duplicates (the Story normalizes state).
 * - Prints site count and locales before/after seeding for quick diagnostics.
 */

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

((function (): int {
    $start = microtime(true);
    $stdout = fopen('php://stdout', 'w');
    $stderr = fopen('php://stderr', 'w');
    $out = static fn (string $msg) => fwrite(
        $stdout,
        $msg.(str_ends_with($msg, PHP_EOL) ? '' : PHP_EOL),
    );
    $err = static fn (string $msg) => fwrite(
        $stderr,
        $msg.(str_ends_with($msg, PHP_EOL) ? '' : PHP_EOL),
    );

    $root = dirname(__DIR__);
    $autoload = $root.'/vendor/autoload.php';
    if (!is_file($autoload)) {
        $err(
            '[seed-baseline] FATAL: vendor/autoload.php not found at '.
                $autoload,
        );

        return 1;
    }
    require $autoload;

    // Default to APP_ENV=test if not provided
    $_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'test';
    $_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '0';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '0';

    if (!in_array($_SERVER['APP_ENV'], ['test', 'dev'], true)) {
        $err(
            '[seed-baseline] WARNING: APP_ENV='.
                $_SERVER['APP_ENV'].
                ' (expected test or dev). Proceeding anyway.',
        );
    }

    // Load .env (parity with PHPUnit bootstrap)
    if (class_exists(Dotenv::class)) {
        try {
            new Dotenv()->bootEnv($root.'/.env');
        } catch (Throwable $e) {
            $err(
                '[seed-baseline] WARNING: Dotenv load failed: '.
                    $e->getMessage(),
            );
        }
    }

    $out(
        '[seed-baseline] =====================================================',
    );
    $out(
        '[seed-baseline] Env='.
            $_SERVER['APP_ENV'].
            ' PHP='.
            PHP_VERSION.
            ' time='.
            date('c'),
    );

    // Boot Symfony Kernel
    try {
        $kernel = new Kernel(
            $_SERVER['APP_ENV'],
            (bool) ($_SERVER['APP_DEBUG'] ?? false),
        );
        $kernel->boot();
    } catch (Throwable $e) {
        $err('[seed-baseline] FATAL: Kernel boot failed: '.$e->getMessage());

        return 2;
    }

    // Doctrine EM
    try {
        /** @var Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $kernel->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
    } catch (Throwable $e) {
        $err(
            '[seed-baseline] FATAL: Doctrine not available: '.
                $e->getMessage(),
        );

        return 2;
    }

    $summarize = static function (
        Doctrine\Persistence\ObjectManager $em,
    ): array {
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        $siteCount = method_exists($siteRepo, 'count')
            ? $siteRepo->count([])
            : count($siteRepo->findAll());
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

        // Root page presence per site (quick health)
        $roots = [];
        foreach ($sites as $s) {
            $loc = method_exists($s, 'getLocale')
                ? (string) $s->getLocale()
                : 'n/a';
            $root = method_exists($pageRepo, 'findOneBy')
                ? $pageRepo->findOneBy(['site' => $s, 'url' => '/'])
                : null;
            $roots[$loc] = null !== $root;
        }

        return [$siteCount, $locales, $roots];
    };

    // Before
    [$beforeCount, $beforeLocales, $beforeRoots] = $summarize($em);
    $out(
        sprintf(
            '[seed-baseline] Before: sites=%d locales=[%s]',
            $beforeCount,
            implode(', ', $beforeLocales),
        ),
    );
    if ($beforeLocales) {
        $out(
            '[seed-baseline] Root page presence (before): '.
                json_encode($beforeRoots, JSON_UNESCAPED_SLASHES),
        );
    }

    // Load Story
    $storyClass = 'App\\Story\\CmsBaselineStory';
    if (!class_exists($storyClass)) {
        $err('[seed-baseline] FATAL: Story class not found: '.$storyClass);

        return 3;
    }

    try {
        /* @var class-string $storyClass */
        $out('[seed-baseline] Loading CmsBaselineStory...');
        $storyClass::load();
        $out('[seed-baseline] CmsBaselineStory load() invoked.');
    } catch (Throwable $e) {
        $err(
            '[seed-baseline] FATAL: Loading CmsBaselineStory failed: '.
                $e->getMessage(),
        );

        return 4;
    }

    // After
    // Clear EM to avoid stale state if Story used custom repos
    try {
        if (method_exists($em, 'clear')) {
            $em->clear();
        }
    } catch (Throwable) {
        // ignore
    }

    [$afterCount, $afterLocales, $afterRoots] = $summarize($em);
    $out(
        sprintf(
            '[seed-baseline] After:  sites=%d locales=[%s]',
            $afterCount,
            implode(', ', $afterLocales),
        ),
    );
    if ($afterLocales) {
        $out(
            '[seed-baseline] Root page presence (after): '.
                json_encode($afterRoots, JSON_UNESCAPED_SLASHES),
        );
    }

    // Simple verdict
    if (2 === $afterCount && $afterLocales === ['en', 'fi']) {
        $out('[seed-baseline] OK: FI + EN sites present.');
    } else {
        $err(
            '[seed-baseline] WARNING: Unexpected site state. Expected 2 sites [en, fi].',
        );
    }

    // Duration + shutdown
    $durMs = (int) round((microtime(true) - $start) * 1000);
    $out('[seed-baseline] Duration: '.$durMs.'ms');
    $out(
        '[seed-baseline] =====================================================',
    );

    try {
        $kernel->shutdown();
    } catch (Throwable) {
        // ignore
    }

    // Exit success even if state not ideal; this is a helper script.
    return 0;
})()
    && exit(0))
    || exit(0);
