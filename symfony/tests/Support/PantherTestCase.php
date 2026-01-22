<?php

declare(strict_types=1);

namespace App\Tests\Support;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Panther\Client as PantherClient;
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
#[SkipDatabaseRollback]
abstract class PantherTestCase extends BasePantherTestCase
{
    use Factories;

    protected static ?KernelInterface $pantherKernel = null;
    protected static bool $driversInstalled = false;
    protected static array $previousEnv = [];
    protected static array $previousServer = [];
    protected static array $previousGetEnv = [];
    protected static ?string $pantherDbPath = null;
    private static ?int $webServerPort = null;
    private static ?int $chromeDriverPort = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$driversInstalled) {
            $this->installBrowserDrivers();
        }

        $this->bootstrapPantherEnvironment();
        $this->clearErrorScreenshots();
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

    protected function getPantherWebServerPort(): int
    {
        return self::$webServerPort ??= self::determinePort(9080);
    }

    protected static function createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = []): PantherClient
    {
        $options['port'] ??= self::$webServerPort ??= self::determinePort(9080);
        $managerOptions['port'] ??= self::$chromeDriverPort ??= self::determinePort(9515);

        return parent::createPantherClient($options, $kernelOptions, $managerOptions);
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
        $cacheSuffix = self::cacheKey();
        $cachePath = $projectDir.'/var/cache/panther_'.$cacheSuffix;
        $_SERVER['PANTHER_CACHE_DIR'] = $cachePath;
        putenv('PANTHER_CACHE_DIR='.$cachePath);
        $_SERVER['PANTHER_LOG_DIR'] = $projectDir.'/var/log/panther_'.$cacheSuffix;
        putenv('PANTHER_LOG_DIR='.$_SERVER['PANTHER_LOG_DIR']);
        self::$webServerPort ??= self::determinePort(9080);
        self::$chromeDriverPort ??= self::determinePort(9515);
        $_SERVER['PANTHER_WEB_SERVER_PORT'] = (string) self::$webServerPort;
        putenv('PANTHER_WEB_SERVER_PORT='.(string) self::$webServerPort);

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
            'PANTHER_CACHE_DIR' => $_ENV['PANTHER_CACHE_DIR'] ?? null,
            'PANTHER_LOG_DIR' => $_ENV['PANTHER_LOG_DIR'] ?? null,
            'PANTHER_WEB_SERVER_PORT' => $_ENV['PANTHER_WEB_SERVER_PORT'] ?? null,
        ];
        self::$previousServer = [
            'APP_ENV' => $_SERVER['APP_ENV'] ?? null,
            'DATABASE_URL' => $_SERVER['DATABASE_URL'] ?? null,
            'PANTHER_CACHE_DIR' => $_SERVER['PANTHER_CACHE_DIR'] ?? null,
            'PANTHER_LOG_DIR' => $_SERVER['PANTHER_LOG_DIR'] ?? null,
            'PANTHER_WEB_SERVER_PORT' => $_SERVER['PANTHER_WEB_SERVER_PORT'] ?? null,
        ];
        self::$previousGetEnv = [
            'APP_ENV' => false !== getenv('APP_ENV') ? getenv('APP_ENV') : null,
            'DATABASE_URL' => false !== getenv('DATABASE_URL') ? getenv('DATABASE_URL') : null,
            'PANTHER_CACHE_DIR' => false !== getenv('PANTHER_CACHE_DIR') ? getenv('PANTHER_CACHE_DIR') : null,
            'PANTHER_LOG_DIR' => false !== getenv('PANTHER_LOG_DIR') ? getenv('PANTHER_LOG_DIR') : null,
            'PANTHER_WEB_SERVER_PORT' => false !== getenv('PANTHER_WEB_SERVER_PORT') ? getenv('PANTHER_WEB_SERVER_PORT') : null,
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
        foreach (['DATABASE_URL', 'PANTHER_CACHE_DIR', 'PANTHER_LOG_DIR', 'PANTHER_WEB_SERVER_PORT'] as $key) {
            if (\array_key_exists($key, self::$previousServer)) {
                $value = self::$previousServer[$key];
                if (null === $value) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }

        // Restore getenv()
        if (\array_key_exists('APP_ENV', self::$previousGetEnv)) {
            $value = self::$previousGetEnv['APP_ENV'];
            putenv(null === $value ? 'APP_ENV' : 'APP_ENV='.$value);
        }
        foreach (['DATABASE_URL', 'PANTHER_CACHE_DIR', 'PANTHER_LOG_DIR', 'PANTHER_WEB_SERVER_PORT'] as $key) {
            if (\array_key_exists($key, self::$previousGetEnv)) {
                $value = self::$previousGetEnv[$key];
                putenv(null === $value ? $key : $key.'='.$value);
            }
        }

        self::$previousEnv = self::$previousServer = self::$previousGetEnv = [];
        self::$webServerPort = null;
        self::$chromeDriverPort = null;
    }

    /**
     * Get the Panther kernel (available after setUp).
     */
    protected function getPantherKernel(): ?KernelInterface
    {
        return self::$pantherKernel;
    }

    private static function findAvailablePort(int $preferred, int $attempts = 50): int
    {
        for ($i = 0; $i < $attempts; ++$i) {
            $candidate = $preferred + $i;
            if (self::isPortFree($candidate)) {
                return $candidate;
            }
        }

        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (false === $socket) {
            throw new \RuntimeException(\sprintf('Unable to allocate a free TCP port (error %d: %s).', $errno, $errstr));
        }
        $name = stream_socket_get_name($socket, false) ?: '';
        @fclose($socket);

        if (false === ($pos = strrpos($name, ':'))) {
            throw new \RuntimeException('Unable to parse dynamically allocated port name: '.$name);
        }

        return (int) substr($name, $pos + 1);
    }

    private static function isPortFree(int $port): bool
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $errstr);
        if (false === $socket) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private static function determinePort(int $base): int
    {
        $candidates = [];
        if ($token = self::paratestToken()) {
            $candidates[] = $base + self::tokenOffset($token);
        }
        $candidates[] = $base + (getmypid() % 1000);

        foreach ($candidates as $candidate) {
            if (self::isPortFree($candidate)) {
                return $candidate;
            }
        }

        return self::findAvailablePort($base);
    }

    private static function tokenOffset(string $token): int
    {
        if (ctype_digit($token)) {
            return (int) $token;
        }

        return hexdec(substr(md5($token), 0, 4)) % 500;
    }

    private static function paratestToken(): ?string
    {
        $token = getenv('TEST_TOKEN') ?: getenv('PARATEST_TEST_TOKEN');

        return \is_string($token) && '' !== $token ? $token : null;
    }

    private static function cacheKey(): string
    {
        return self::paratestToken() ?? (string) getmypid();
    }

    private function clearErrorScreenshots(): void
    {
        $dir = $this->resolveErrorScreenshotDir();
        if (null === $dir) {
            return;
        }

        $filesystem = new Filesystem();
        try {
            if ($filesystem->exists($dir)) {
                $filesystem->remove($dir);
            }
            $filesystem->mkdir($dir);
        } catch (\Throwable $e) {
            // Best effort cleanup to avoid masking test failures.
        }
    }

    private function resolveErrorScreenshotDir(): ?string
    {
        $dir = $_SERVER['PANTHER_ERROR_SCREENSHOT_DIR']
            ?? getenv('PANTHER_ERROR_SCREENSHOT_DIR')
            ?? null;
        if (!\is_string($dir) || '' === $dir) {
            return null;
        }

        if (!str_starts_with($dir, '/')) {
            $dir = $this->getProjectDir().'/'.ltrim($dir, './');
        }

        return $dir;
    }
}
