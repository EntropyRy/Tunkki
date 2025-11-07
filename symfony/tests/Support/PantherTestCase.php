<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Panther\PantherTestCase as BasePantherTestCase;
use Symfony\Component\Process\Process;
use Zenstruck\Foundry\Test\Factories;

/**
 * Base class for Panther (browser) tests with temporary database support.
 *
 * Handles:
 * - SQLite database in system temp directory for isolated tests
 * - Automatic database cleanup between tests
 * - Schema creation/recreation
 * - Environment isolation (APP_ENV=panther)
 * - CMS baseline seeding
 *
 * Database Strategy:
 * - Uses sys_get_temp_dir() for database file (typically /tmp or /dev/shm)
 * - Each test process gets a unique database (PID-based naming)
 * - File-based (not pure :memory:) because Panther's web server runs in separate process
 * - Eliminates var/ directory permission issues
 * - Automatic cleanup in tearDown()
 */
abstract class PantherTestCase extends BasePantherTestCase
{
    use Factories;

    protected static ?KernelInterface $pantherKernel = null;
    protected static bool $driversInstalled = false;
    protected static array $previousEnv = [];
    protected static array $previousServer = [];
    protected static array $previousGetEnv = [];
    protected static ?string $pantherDbPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$driversInstalled) {
            $this->installBrowserDrivers();
        }

        $this->bootstrapPantherEnvironment();
    }

    protected function tearDown(): void
    {
        $this->restoreOriginalEnvironment();

        if (null !== self::$pantherKernel) {
            self::$pantherKernel->shutdown();
            self::$pantherKernel = null;
        }

        // Clean up shared memory database file
        if (null !== self::$pantherDbPath && file_exists(self::$pantherDbPath)) {
            $filesystem = new Filesystem();
            try {
                // Remove database and its WAL files
                $filesystem->remove([
                    self::$pantherDbPath,
                    self::$pantherDbPath.'-shm',
                    self::$pantherDbPath.'-wal',
                    self::$pantherDbPath.'-journal',
                ]);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
            self::$pantherDbPath = null;
        }

        parent::tearDown();
    }

    protected function getProjectDir(): string
    {
        // Override in subclass if needed (e.g., different nesting level)
        return \dirname(__DIR__, 2);
    }

    private function installBrowserDrivers(): void
    {
        $process = new Process(['vendor/bin/bdi', 'detect', 'drivers']);
        $process->setWorkingDirectory($this->getProjectDir());
        $process->mustRun();
        self::$driversInstalled = true;
    }

    private function bootstrapPantherEnvironment(): void
    {
        $projectDir = $this->getProjectDir();
        $filesystem = new Filesystem();
        $cachePath = $projectDir.'/var/cache/panther';

        // Use /tmp for database (writable by both test process and Panther's web server)
        // Unique per-process to allow parallel test execution
        $dbPath = sys_get_temp_dir().'/test_panther_'.getmypid().'.db';
        self::$pantherDbPath = $dbPath;

        // Ensure kernel shutdown
        self::ensureKernelShutdown();

        if (null !== self::$pantherKernel) {
            self::$pantherKernel->shutdown();
            self::$pantherKernel = null;
        }

        // Clean cache
        if ($filesystem->exists($cachePath)) {
            try {
                $filesystem->remove($cachePath);
            } catch (\Throwable $e) {
                // Ignore permission errors on cleanup
            }
        }

        // Clean SQLite database
        if ($filesystem->exists($dbPath)) {
            try {
                // Try to unlock and remove
                if (is_writable($dbPath)) {
                    $filesystem->remove($dbPath);
                } else {
                    // Force permissions if possible
                    @chmod($dbPath, 0666);
                    $filesystem->remove($dbPath);
                }
            } catch (\Throwable $e) {
                // If removal fails, try to drop schema instead
                $this->dropExistingSchema($dbPath);
            }
        }

        // Also remove WAL files if they exist (SQLite Write-Ahead Logging)
        foreach (["{$dbPath}-shm", "{$dbPath}-wal"] as $walFile) {
            if ($filesystem->exists($walFile)) {
                try {
                    @chmod($walFile, 0666);
                    $filesystem->remove($walFile);
                } catch (\Throwable $e) {
                    // Ignore WAL cleanup errors
                }
            }
        }

        // Backup and override environment
        self::$previousEnv = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? null,
            'DATABASE_URL' => $_ENV['DATABASE_URL'] ?? null,
        ];
        self::$previousServer = [
            'APP_ENV' => $_SERVER['APP_ENV'] ?? null,
            'DATABASE_URL' => $_SERVER['DATABASE_URL'] ?? null,
        ];
        self::$previousGetEnv = [
            'APP_ENV' => false !== getenv('APP_ENV') ? getenv('APP_ENV') : null,
            'DATABASE_URL' => false !== getenv('DATABASE_URL') ? getenv('DATABASE_URL') : null,
        ];

        $_ENV['APP_ENV'] = 'panther';
        $_SERVER['APP_ENV'] = 'panther';
        putenv('APP_ENV=panther');
        $_ENV['DATABASE_URL'] = 'sqlite:///'.$dbPath;
        $_SERVER['DATABASE_URL'] = 'sqlite:///'.$dbPath;
        putenv('DATABASE_URL=sqlite:///'.$dbPath);

        // Boot kernel
        $kernel = new \App\Kernel('panther', true);
        $kernel->boot();
        self::$pantherKernel = $kernel;

        // Create schema
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        if ([] !== $metadata) {
            $schemaTool = new SchemaTool($em);

            try {
                // Try to create fresh schema
                $schemaTool->createSchema($metadata);
            } catch (\Throwable $e) {
                // If creation fails (tables exist), drop and recreate
                try {
                    $schemaTool->dropSchema($metadata);
                    $schemaTool->createSchema($metadata);
                } catch (\Throwable $dropError) {
                    // Last resort: force recreate by closing connection and retrying
                    $em->getConnection()->close();
                    $schemaTool->createSchema($metadata);
                }
            }
        }

        // Run CMS seed commands
        $application = new Application($kernel);
        $application->setAutoExit(false);

        foreach ([
            'entropy:cms:seed',
            'sonata:page:update-core-routes',
        ] as $command) {
            try {
                $application->run(new ArrayInput(['command' => $command]), new NullOutput());
            } catch (\Throwable $e) {
                // Continue even if CMS commands fail (might not be critical for all tests)
            }
        }

        // Ensure database file is writable
        if (file_exists($dbPath)) {
            @chmod($dbPath, 0666);
        }
    }

    private function dropExistingSchema(string $dbPath): void
    {
        try {
            // Try to connect and drop existing schema
            $pdo = new \PDO('sqlite:'.$dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Get all tables
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(\PDO::FETCH_COLUMN);

            // Drop all tables
            $pdo->exec('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
            }
            $pdo->exec('PRAGMA foreign_keys = ON');

            $pdo = null; // Close connection
        } catch (\Throwable $e) {
            // If dropping fails, ignore (will be handled by createSchema)
        }
    }

    private function restoreOriginalEnvironment(): void
    {
        if ([] === self::$previousEnv && [] === self::$previousServer && [] === self::$previousGetEnv) {
            return;
        }

        // Restore $_ENV
        if (\array_key_exists('APP_ENV', self::$previousEnv)) {
            $value = self::$previousEnv['APP_ENV'];
            if (null === $value) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $value;
            }
        }
        if (\array_key_exists('DATABASE_URL', self::$previousEnv)) {
            $value = self::$previousEnv['DATABASE_URL'];
            if (null === $value) {
                unset($_ENV['DATABASE_URL']);
            } else {
                $_ENV['DATABASE_URL'] = $value;
            }
        }

        // Restore $_SERVER
        if (\array_key_exists('APP_ENV', self::$previousServer)) {
            $value = self::$previousServer['APP_ENV'];
            if (null === $value) {
                unset($_SERVER['APP_ENV']);
            } else {
                $_SERVER['APP_ENV'] = $value;
            }
        }
        if (\array_key_exists('DATABASE_URL', self::$previousServer)) {
            $value = self::$previousServer['DATABASE_URL'];
            if (null === $value) {
                unset($_SERVER['DATABASE_URL']);
            } else {
                $_SERVER['DATABASE_URL'] = $value;
            }
        }

        // Restore getenv()
        if (\array_key_exists('APP_ENV', self::$previousGetEnv)) {
            $value = self::$previousGetEnv['APP_ENV'];
            putenv(null === $value ? 'APP_ENV' : 'APP_ENV='.$value);
        }
        if (\array_key_exists('DATABASE_URL', self::$previousGetEnv)) {
            $value = self::$previousGetEnv['DATABASE_URL'];
            putenv(null === $value ? 'DATABASE_URL' : 'DATABASE_URL='.$value);
        }

        self::$previousEnv = self::$previousServer = self::$previousGetEnv = [];
    }

    /**
     * Get the Panther kernel (available after setUp).
     */
    protected function getPantherKernel(): ?KernelInterface
    {
        return self::$pantherKernel;
    }
}
