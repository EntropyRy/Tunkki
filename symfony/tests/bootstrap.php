<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
    @fwrite(\STDERR, "[bootstrap] FATAL: Expected APP_ENV=test; got '".($_SERVER['APP_ENV'] ?? 'undefined')."'. Use 'make test' (Makefile enforces -e APP_ENV=test) or pass -e APP_ENV=test to docker compose exec.\n");
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
 *  - Idempotent: entropy:cms:seed can run on every bootstrap; snapshots can be recreated safely.
 *  - Advisory lock (key 1220304): Serializes entire CMS baseline setup across parallel processes.
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

        // Acquire advisory lock for ENTIRE CMS baseline setup (seed + snapshots + enable)
        // to prevent parallel execution races across all three operations
        $lockAcquired = false;
        $lockKey = 1220304; // Unique key for bootstrap CMS setup
        if ($container->has('doctrine')) {
            $doctrine = $container->get('doctrine');
            if (method_exists($doctrine, 'getConnection')) {
                $conn = $doctrine->getConnection();
                $platform = $conn->getDatabasePlatform();

                try {
                    if ($platform instanceof PostgreSQLPlatform) {
                        $result = $conn->fetchOne('SELECT pg_try_advisory_lock(?)', [$lockKey]);
                        $lockAcquired = (bool) $result;
                    } elseif ($platform instanceof AbstractMySQLPlatform) {
                        $result = $conn->fetchOne('SELECT GET_LOCK(?, 0)', ["cms_bootstrap_{$lockKey}"]);
                        $lockAcquired = 1 === $result;
                    }
                } catch (Throwable $le) {
                    // Lock acquisition failed; proceed without lock (will retry below)
                }

                // Retry with exponential backoff if initial acquisition failed
                if (!$lockAcquired) {
                    for ($attempt = 1; $attempt <= 5; ++$attempt) {
                        sleep(1); // Wait 1s between attempts
                        try {
                            if ($platform instanceof PostgreSQLPlatform) {
                                $result = $conn->fetchOne('SELECT pg_try_advisory_lock(?)', [$lockKey]);
                                $lockAcquired = (bool) $result;
                            } elseif ($platform instanceof AbstractMySQLPlatform) {
                                $result = $conn->fetchOne('SELECT GET_LOCK(?, 0)', ["cms_bootstrap_{$lockKey}"]);
                                $lockAcquired = 1 === $result;
                            }
                            if ($lockAcquired) {
                                break;
                            }
                        } catch (Throwable $le) {
                            // Continue retrying
                        }
                    }
                }

                // If we acquired the lock, we'll do the setup
                // If we didn't acquire it after retries, another process is done (lock was released), so we skip
                if (!$lockAcquired) {
                    // At this point, the first process should have completed setup and released the lock
                    // We couldn't acquire it even after 5 attempts, meaning it's busy OR just completed
                    // Try one final blocking acquisition with short timeout to ensure setup is truly complete
                    try {
                        if ($platform instanceof AbstractMySQLPlatform) {
                            // Blocking acquisition with 10-second timeout
                            $result = $conn->fetchOne('SELECT GET_LOCK(?, 10)', ["cms_bootstrap_{$lockKey}"]);
                            if (1 === $result) {
                                // We got the lock, meaning the first process finished. Release immediately and skip.
                                $conn->executeStatement('SELECT RELEASE_LOCK(?)', ["cms_bootstrap_{$lockKey}"]);
                                @fwrite(\STDERR, "[bootstrap] INFO: CMS baseline setup completed by another process. Continuing.\n");

                                return;
                            }
                        } elseif ($platform instanceof PostgreSQLPlatform) {
                            // PostgreSQL blocking lock with timeout (simulated with retries)
                            for ($i = 0; $i < 10; ++$i) {
                                $result = $conn->fetchOne('SELECT pg_try_advisory_lock(?)', [$lockKey]);
                                if ($result) {
                                    $conn->executeStatement('SELECT pg_advisory_unlock(?)', [$lockKey]);
                                    @fwrite(\STDERR, "[bootstrap] INFO: CMS baseline setup completed by another process. Continuing.\n");

                                    return;
                                }
                                sleep(1);
                            }
                        }
                    } catch (Throwable $le) {
                        // Couldn't verify completion; proceed anyway assuming idempotent setup
                    }

                    @fwrite(\STDERR, "[bootstrap] WARNING: Could not acquire CMS setup lock. Assuming setup complete.\n");

                    return;
                }
            }
        }

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

        if (null === $siteCount || 0 === (int) $siteCount) {
            $application = new Application($kernel);
            $application->setAutoExit(false);

            // Seed CMS baseline (idempotent, snapshots included)
            $application->run(new ArrayInput(['command' => 'entropy:cms:seed', '-q' => true]), new NullOutput());
        }

        // entropy:cms:seed now refreshes snapshots; no manual toggles required.
    } catch (Throwable $e) {
        @fwrite(\STDERR, '[bootstrap] WARNING: CMS seed/snapshot step failed: '.$e->getMessage()."\n");
    } finally {
        // Release advisory lock
        if (isset($lockAcquired) && $lockAcquired && isset($container) && $container->has('doctrine')) {
            try {
                $doctrine = $container->get('doctrine');
                $conn = $doctrine->getConnection();
                $platform = $conn->getDatabasePlatform();

                if ($platform instanceof PostgreSQLPlatform) {
                    $conn->executeStatement('SELECT pg_advisory_unlock(?)', [$lockKey]);
                } elseif ($platform instanceof AbstractMySQLPlatform) {
                    $conn->executeStatement('SELECT RELEASE_LOCK(?)', ["cms_bootstrap_{$lockKey}"]);
                }
            } catch (Throwable $le) {
                // Best effort; lock auto-releases on connection close
            }
        }
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
        @fwrite(\STDERR, '[bootstrap] WARNING: Kernel boot failed before story load: '.$e->getMessage()."\n");
    }

    try {
        $manager = StoryManager::instance();
        // If a known reference already exists, assume story loaded (prevents redundant work in edge cases).
        if ($manager->has('cms:site:default')) {
            @fwrite(\STDERR, "[bootstrap] INFO: CmsBaselineStory reference already present (cms:site:default); skipping load.\n");

            return;
        }
        /* @var class-string $storyClass */
        @fwrite(\STDERR, "[bootstrap] INFO: Loading CmsBaselineStory...\n");
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
                                    @fwrite(\STDERR, '[bootstrap] WARNING: Snapshot creation failed for a site: '.$e->getMessage()."\n");
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                @fwrite(\STDERR, '[bootstrap] WARNING: Snapshot generation skipped: '.$e->getMessage()."\n");
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
                @fwrite(\STDERR, '[bootstrap] WARNING: Post-story site count check failed: '.$e->getMessage()."\n");
            }
        }

        if (is_int($siteCount)) {
            if (2 !== $siteCount) {
                @fwrite(\STDERR, '[bootstrap] WARNING: After CmsBaselineStory load, site count='.$siteCount.' (expected 2)'."\n");
            } else {
                @fwrite(\STDERR, "[bootstrap] INFO: CmsBaselineStory loaded; site count=2\n");
            }
        }
    } catch (Throwable $e) {
        // Non-fatal: log to STDERR for visibility without breaking the suite.
        @fwrite(\STDERR, '[bootstrap] WARNING: Failed loading CmsBaselineStory: '.$e->getMessage()."\n");
    }
})();
