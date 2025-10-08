<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

// Autoload vendor (Foundry, Symfony, etc.)
require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if (!isset($_SERVER['APP_ENV']) || 'test' !== $_SERVER['APP_ENV']) {
    @fwrite(STDERR, "[bootstrap] FATAL: Expected APP_ENV=test; got '".($_SERVER['APP_ENV'] ?? 'undefined')."'. Use 'make test' (Makefile enforces -e APP_ENV=test) or pass -e APP_ENV=test to docker compose exec.\n");
    throw new RuntimeException('Tests must run with APP_ENV=test');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

/**
 * Seed minimal CMS (Sites + core Pages) and create snapshots once for the entire test run.
 * This replaces legacy per-test seeding and Foundry Story reliance with stable console commands.
 *
 * Safety Guards:
 *  - Wrapped in try/catch: failures are logged to STDERR without breaking the suite.
 *  - Idempotent: cms:seed:minimal can run on every bootstrap; snapshots can be recreated safely.
 */
(function (): void {
    // Seed minimal CMS and ensure snapshots exist (front page routing depends on snapshots in this setup).
    try {
        // Boot kernel if not already booted
        $env = $_SERVER['APP_ENV'] ?? 'test';
        $debug = (bool) ($_SERVER['APP_DEBUG'] ?? false);
        if (!isset($kernel) || !isset($kernelBooted) || !$kernelBooted) {
            if (class_exists(Kernel::class)) {
                $kernel = new Kernel($env, $debug);
                $kernel->boot();
                $kernelBooted = true;
            }
        }

        $container = $kernel->getContainer();

        // Check if CMS sites already exist; if none, seed
        $siteCount = null;
        if ($container->has('doctrine')) {
            $doctrine = $container->get('doctrine');
            if (method_exists($doctrine, 'getManager')) {
                $em = $doctrine->getManager();
                $repo = $em->getRepository('App\Entity\Sonata\SonataPageSite');
                $siteCount = method_exists($repo, 'count') ? $repo->count([]) : count($repo->findAll());
            }
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        // Always seed minimal CMS (idempotent)
        $application->run(new ArrayInput(['command' => 'cms:seed:minimal', '-q' => true]), new NullOutput());

        // Always ensure snapshots exist for front-end page resolution
        $application->run(new ArrayInput(['command' => 'sonata:page:create-snapshots', '-q' => true]), new NullOutput());

        // Force-enable all snapshots in tests (direct_publication may not affect routing resolution)
        // This ensures DynamicRouter can resolve pages even if snapshots were created disabled.
        try {
            if (isset($container) && $container->has('doctrine')) {
                $doctrine = $container->get('doctrine');

                $conn = null;
                if (method_exists($doctrine, 'getConnection')) {
                    $conn = $doctrine->getConnection();
                } elseif (method_exists($doctrine, 'getManager')) {
                    $em = $doctrine->getManager();
                    if (is_object($em) && method_exists($em, 'getConnection')) {
                        $conn = $em->getConnection();
                    }
                }

                if ($conn) {
                    // Simple approach for tests: publish all snapshots
                    $conn->executeStatement('UPDATE page__snapshot SET enabled = 1');
                }
            }
        } catch (Throwable $ee) {
            @fwrite(STDERR, '[bootstrap] WARNING: Forcing page__snapshot.enabled=1 failed: '.$ee->getMessage()."\n");
        }
    } catch (Throwable $e) {
        @fwrite(STDERR, '[bootstrap] WARNING: CMS seed/snapshot step failed: '.$e->getMessage()."\n");
    }

    // Stop here; skip legacy Foundry Story path entirely.
    return;

    // Boot Symfony kernel so Foundry factories have container/Doctrine available during story load
    $kernelBooted = false;
    try {
        if (class_exists(Kernel::class)) {
            $env = $_SERVER['APP_ENV'] ?? 'test';
            $debug = (bool) ($_SERVER['APP_DEBUG'] ?? false);
            $kernel = new Kernel($env, $debug);
            $kernel->boot();
            $kernelBooted = true;
        }
    } catch (Throwable $e) {
        @fwrite(STDERR, '[bootstrap] WARNING: Kernel boot failed before story load: '.$e->getMessage()."\n");
    }

    try {
        $manager = StoryManager::instance();
        // If a known reference already exists, assume story loaded (prevents redundant work in edge cases).
        if ($manager->has('cms:site:default')) {
            @fwrite(STDERR, "[bootstrap] INFO: CmsBaselineStory reference already present (cms:site:default); skipping load.\n");

            return;
        }
        /* @var class-string $storyClass */
        @fwrite(STDERR, "[bootstrap] INFO: Loading CmsBaselineStory...\n");
        $storyClass::load();

        // After baseline seeding, generate snapshots per site once to enable front-end resolution.
        // Even though tests set direct_publication=true, some routing setups may still rely on snapshots.
        if (isset($kernel) && !empty($kernelBooted)) {
            try {
                $container = $kernel->getContainer();
                if ($container->has('doctrine') && $container->has('sonata.page.service.create_snapshot')) {
                    $doctrine = $container->get('doctrine');
                    if (method_exists($doctrine, 'getManager')) {
                        $em = $doctrine->getManager();
                        $siteRepo = $em->getRepository('App\Entity\Sonata\SonataPageSite');
                        $sites = method_exists($siteRepo, 'findAll') ? $siteRepo->findAll() : [];
                        $createSnapshot = $container->get('sonata.page.service.create_snapshot');

                        if (method_exists($createSnapshot, 'createBySite')) {
                            foreach ($sites as $site) {
                                try {
                                    $createSnapshot->createBySite($site);
                                } catch (Throwable $e) {
                                    @fwrite(STDERR, '[bootstrap] WARNING: Snapshot creation failed for a site: '.$e->getMessage()."\n");
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                @fwrite(STDERR, '[bootstrap] WARNING: Snapshot generation skipped: '.$e->getMessage()."\n");
            }
        }

        // Verify site count after story load (debug only)
        $siteCount = null;
        if (isset($kernel) && !empty($kernelBooted)) {
            try {
                $container = $kernel->getContainer();
                if ($container->has('doctrine')) {
                    $doctrine = $container->get('doctrine');
                    if (method_exists($doctrine, 'getManager')) {
                        $em = $doctrine->getManager();
                        $repo = $em->getRepository('App\Entity\Sonata\SonataPageSite');
                        if (method_exists($repo, 'count')) {
                            $siteCount = $repo->count([]);
                        } else {
                            $all = method_exists($repo, 'findAll') ? $repo->findAll() : [];
                            $siteCount = is_array($all) ? count($all) : null;
                        }
                    }
                }
            } catch (Throwable $e) {
                @fwrite(STDERR, '[bootstrap] WARNING: Post-story site count check failed: '.$e->getMessage()."\n");
            }
        }

        if (is_int($siteCount)) {
            if (2 !== $siteCount) {
                @fwrite(STDERR, '[bootstrap] WARNING: After CmsBaselineStory load, site count='.$siteCount.' (expected 2)'."\n");
            } else {
                @fwrite(STDERR, "[bootstrap] INFO: CmsBaselineStory loaded; site count=2\n");
            }
        }
    } catch (Throwable $e) {
        // Non-fatal: log to STDERR for visibility without breaking the suite.
        @fwrite(STDERR, '[bootstrap] WARNING: Failed loading CmsBaselineStory: '.$e->getMessage()."\n");
    }
})();
