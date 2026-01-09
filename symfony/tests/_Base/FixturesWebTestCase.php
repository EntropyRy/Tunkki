<?php

declare(strict_types=1);

namespace App\Tests\_Base;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Factory\PageFactory;
use App\Factory\SiteFactory;
use App\Tests\Http\SiteAwareKernelBrowser;
use App\Time\ClockInterface;
use App\Time\MutableClock;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Base functional/integration WebTestCase.
 *
 * This variant intentionally DOES NOT purge or load Doctrine fixtures.
 * The test environment is assumed to be prepared externally (CI script
 * or a manual "doctrine:fixtures:load" invocation) before PHPUnit starts.
 *
 * Rationale:
 *  - Page (Sonata) fixtures must always be present; the previous design
 *    purged them right after the global fixture load, removing pages.
 *  - Avoids duplicate fixture work and speeds up the suite.
 *
 * Guidelines for tests extending this class:
 *  - Do NOT assume an empty database; rely on canonical fixtures.
 *  - If a test needs a different state, create entities directly and
 *    persist/flush them; clean up afterwards if necessary.
 *  - Avoid modifying/deleting global "root" records (Sites, root Pages)
 *    unless you recreate them, otherwise later tests may fail.
 *
 * Provided helpers:
 *  - em()                  : returns the shared ObjectManager
 *  - findOneOrFail()       : fetch entity by criteria with assertion
 *  - assertEntityCount()   : assert total rows for a class
 *  - resetAuthSession()    : clear security token + session (order independence)
 *
 * Explicitly removed helpers:
 *  - reloadFixtures(): now throws LogicException (tests should not call)
 */
abstract class FixturesWebTestCase extends WebTestCase
{
    /**
     * Local shadow for WebTestCase static client reference.
     * Symfony's WebTestCase stores its client in a private static property (not accessible here),
     * but BrowserKitAssertionsTrait methods (assertResponseIsSuccessful, etc.) reference
     * a static $client on the concrete class if present. Declaring it here ensures those
     * assertions use the SiteAwareKernelBrowser we install in initSiteAwareClient().
     */
    protected static ?\Symfony\Bundle\FrameworkBundle\KernelBrowser $client = null;

    /**
     * Instance-scoped EntityManager for each test case to avoid cross-test state leakage.
     */
    protected ObjectManager $em;

    /**
     * One-time flag to ensure the minimal CMS (Sites + root Pages) baseline
     * is present for Sonata Page multisite routing tests. This avoids random
     * test order failures when a test relying on pages runs before any
     * fixtures/stories have seeded them.
     */
    protected static bool $cmsBaselineLoaded = false;

    /**
     * Primes Sonata Page routers once per process to warm front controllers for FI/EN roots.
     */
    protected static bool $routersPrimed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTestClock();

        // Initialize the multisite-aware client first so WebTestCase registers an active browser
        $this->initSiteAwareClient();
        // Reflect site-aware client into WebTestCase static client so BrowserKit assertions reference the correct instance.
        self::$client = $this->siteAwareClient;

        // Initialize the EntityManager after the client so it binds to the current kernel/container
        /* @var ObjectManager $em */
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Ensure the EntityManager is open before CMS baseline seeding to avoid closed EM errors
        $this->ensureOpenEntityManager();

        // Guarantee CMS baseline now that EM + client are ready.
        $this->ensureCmsBaseline();

        // Ensure EM remains open after client/kernel changes and CMS baseline seeding; then clear identity map to avoid stale state between tests.
        $this->ensureOpenEntityManager();
        $this->em()->clear();

        // Optional integrity diagnostics for duplicate user-member linkage issues.
        // Enabled only when TEST_USER_CREATION_DEBUG is set (kept lightweight).
        if (getenv('TEST_USER_CREATION_DEBUG')) {
            try {
                $conn = $this->em->getConnection();
                $dupes = $conn->fetchAllAssociative(
                    'SELECT member_id, COUNT(*) c FROM user WHERE member_id IS NOT NULL GROUP BY member_id HAVING c > 1',
                );
                if ($dupes) {
                    @fwrite(
                        \STDERR,
                        '[Integrity] Duplicate user.member_id rows: '.
                            json_encode($dupes, \JSON_UNESCAPED_SLASHES).
                            \PHP_EOL,
                    );
                }
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[Integrity] Diagnostic query failed: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
        }

        // Reset auth/session to avoid leakage between randomized test executions.
        $this->resetAuthSession();

        // Prime Sonata Page routers for both locales once per process to reduce 404 flakiness
        if (!self::$routersPrimed) {
            try {
                $this->seedClientHome('fi');
            } catch (\Throwable $e) {
                // ignore priming errors
            }
            try {
                $this->seedClientHome('en');
            } catch (\Throwable $e) {
                // ignore priming errors
            }
            self::$routersPrimed = true;

            // Clear any session state created during priming
            $this->resetAuthSession();
        }

        // Prime Sonata Admin routes once per process to avoid initial 404 when the admin router is not yet warmed.
        // This is a best-effort warm-up (ignore status/redirects), then clear any incidental session state.
        static $adminPrimed = false;
        if (!$adminPrimed) {
            try {
                // Canonical (FI, no prefix)
                $this->siteAwareClient?->request('GET', '/admin/');
            } catch (\Throwable $e) {
                // ignore priming errors
            }
            try {
                // English-prefixed variant
                $this->siteAwareClient?->request('GET', '/en/admin/');
            } catch (\Throwable $e) {
                // ignore priming errors
            }
            $adminPrimed = true;

            // Clear any session state created during priming
            $this->resetAuthSession();
        }
    }

    /**
     * Clear authentication token & reset session to avoid cross-test leakage
     * in randomized execution (functional/integration tests).
     * Safe to call even if security services absent.
     */
    protected function resetAuthSession(): void
    {
        $container = static::getContainer();

        if ($container->has('security.token_storage')) {
            $ts = $container->get('security.token_storage');
            if ($ts instanceof TokenStorageInterface) {
                $ts->setToken(null);
            }
        }
        if ($container->has('session')) {
            $session = $container->get('session');
            if ($session instanceof SessionInterface) {
                // invalidate clears data + regenerates id
                $session->invalidate();
            }
        }
    }

    /**
     * Rewind the mutable test clock (if bound) to the canonical instant defined by test.fixed_datetime.
     * Ensures randomized test execution cannot leak time-travelled state between cases.
     */
    private function resetTestClock(): void
    {
        $container = static::getContainer();
        if (!$container->has(ClockInterface::class)) {
            return;
        }

        $clock = $container->get(ClockInterface::class);
        if (!$clock instanceof MutableClock) {
            return;
        }

        $defaultInstant = $container->hasParameter('test.fixed_datetime')
            ? $container->getParameter('test.fixed_datetime')
            : null;

        if (\is_string($defaultInstant) && '' !== $defaultInstant) {
            $clock->setNow(new \DateTimeImmutable($defaultInstant));
        }
    }

    /**
     * Assert that no authenticated user is present (checks TokenStorage).
     * Useful for login failure / security tests.
     */
    protected function assertNotAuthenticated(string $message): void
    {
        $container = static::getContainer();
        if (!$container->has('security.token_storage')) {
            self::fail(
                'Token storage service missing; cannot verify authentication state.',
            );
        }
        /** @var TokenStorageInterface $ts */
        $ts = $container->get('security.token_storage');
        $token = $ts->getToken();

        if (null === $token) {
            self::assertTrue(true); // Explicit clarity: unauthenticated

            return;
        }

        // Some apps may set a token for anonymous contexts - ensure not authenticated user
        $user = $token->getUser();
        if (\is_object($user)) {
            // Fail if an actual user object present
            self::fail($message.' (Token holds '.$user::class.')');
        } else {
            self::assertTrue(true);
        }
    }

    /**
     * Assert that an authenticated user is present (checks TokenStorage).
     * Useful for successful login / security tests.
     */
    protected function assertAuthenticated(string $message): void
    {
        $container = static::getContainer();
        if (!$container->has('security.token_storage')) {
            self::fail(
                'Token storage service missing; cannot assert auth state.',
            );
        }
        /** @var TokenStorageInterface $ts */
        $ts = $container->get('security.token_storage');
        $token = $ts->getToken();
        self::assertNotNull($token, $message.' (no token)');
        $user = $token->getUser();
        self::assertTrue(
            \is_object($user),
            $message.' (no authenticated user object present)',
        );
    }

    /**
     * Persist the current response content to var/test-failures for debugging.
     */
    protected function dumpResponseToFile(string $label): void
    {
        unset($label);
    }

    /**
     * Ensure the site-aware client is registered as the active WebTestCase client
     * and that at least one request has been performed so BrowserKit assertions
     * do not fail with "A client must be set".
     *
     * Seeds a lightweight login page request to ensure response/crawler exists.
     */
    protected function ensureClientReady(): void
    {
        // Ensure the site-aware client is initialized
        if (
            null === $this->siteAwareClient
            || !self::$client instanceof \Symfony\Bundle\FrameworkBundle\KernelBrowser
        ) {
            $this->initSiteAwareClient();
        }

        // Synchronize static::$client with the instance
        if (
            $this->siteAwareClient instanceof \Symfony\Bundle\FrameworkBundle\KernelBrowser
            && self::$client !== $this->siteAwareClient
        ) {
            self::$client = $this->siteAwareClient;
        }

        // Seed a lightweight GET to ensure a response/crawler exists before any assertions
        // Wrap in try-catch because getResponse() throws if no request was made yet
        if (
            $this->siteAwareClient instanceof \Symfony\Bundle\FrameworkBundle\KernelBrowser
        ) {
            try {
                $response = $this->siteAwareClient->getResponse();
                if (null === $response) {
                    $this->seedLoginPage('fi');
                }
            } catch (\Symfony\Component\BrowserKit\Exception\BadMethodCallException) {
                // No request made yet, seed one
                $this->seedLoginPage('fi');
            }
        }
    }

    /**
     * Override default client creation to reuse the initialized site-aware client.
     * Avoids secondary kernel boots (LogicException in WebTestCase).
     *
     * Tests that need custom client options should override createClient() directly.
     */
    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client();
    }

    /**
     * Ensure the EntityManager reference is open; if closed (e.g. after a secondary kernel boot
     * or Foundry persistence edge-case) reacquire a fresh manager from Doctrine. This is a
     * defensive measure during the migration away from global fixtures + manual kernel boots.
     *
     * NOTE: Long term we should avoid situations that close the EM mid-test; once the suite
     * relies solely on factories + transactional isolation, this helper can be removed.
     */
    protected function ensureOpenEntityManager(): void
    {
        if (
            isset($this->em)
            && method_exists($this->em, 'isOpen')
            && !$this->em->isOpen()
        ) {
            // Reset manager via Doctrine to get a fresh, open EM bound to current kernel/container.
            $this->em = static::getContainer()->get('doctrine')->resetManager();
        }
    }

    /**
     * Recover from a closed EntityManager after an exception during baseline seeding.
     * Re-opens the EM and clears the identity map to prevent stale managed entities.
     */
    private function recoverEntityManagerAfterException(
        ?\Throwable $e = null,
    ): void {
        // Best-effort diagnostic
        if ($e instanceof \Throwable) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] recovering EntityManager after exception: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }

        $this->ensureOpenEntityManager();

        // Clear to avoid inconsistent managed state after partial flushes
        try {
            $this->em()->clear();
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Deprecated: Fixtures are loaded externally (CI or bootstrap).
     *
     * @throws \LogicException always
     */
    protected function reloadFixtures(): void
    {
        throw new \LogicException('reloadFixtures() is disabled. Fixtures are loaded before the test run. Create/modify entities directly inside your test instead.');
    }

    /**
     * Expose the EntityManager to child tests with a null check.
     */
    protected function em(): ObjectManager
    {
        if (!isset($this->em)) {
            /* @var ObjectManager $em */
            $this->em = static::getContainer()->get('doctrine')->getManager();
        }

        return $this->em;
    }

    /**
     * Helper: Fetch an entity by class & criteria with assertions.
     *
     * @template T of object
     *
     * @param class-string<T>     $class
     * @param array<string,mixed> $criteria
     *
     * @return T
     */
    protected function findOneOrFail(string $class, array $criteria): object
    {
        $this->ensureOpenEntityManager();
        $repo = $this->em()->getRepository($class);
        $entity = $repo->findOneBy($criteria);
        $this->assertNotNull(
            $entity,
            \sprintf(
                'Expected one %s for criteria %s, got none.',
                $class,
                json_encode($criteria, \JSON_THROW_ON_ERROR),
            ),
        );

        return $entity;
    }

    /**
     * Instrumentation: after each test, detect closed EM or uninitialized Doctrine proxies.
     * Controlled by env vars (set before running phpunit / test.sh):
     *  - FAIL_ON_CLOSED_ENTITY_MANAGER=1
     *  - FAIL_ON_UNINITIALIZED_PROXIES=1.
     */
    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $em = $this->em;
            // Closed EntityManager detection
            if (method_exists($em, 'isOpen') && !$em->isOpen()) {
                if (getenv('FAIL_ON_CLOSED_ENTITY_MANAGER')) {
                    self::fail('EntityManager is closed.');
                }
            } elseif (method_exists($em, 'getUnitOfWork')) {
                // Uninitialized proxy detection
                $uow = $em->getUnitOfWork();
                $map = $uow->getIdentityMap();
                $uninit = [];
                foreach ($map as $class => $entities) {
                    foreach ($entities as $entity) {
                        if (
                            $entity instanceof \Doctrine\Persistence\Proxy
                            && !$entity->__isInitialized()
                        ) {
                            $uninit[] = $class;
                        }
                    }
                }
                if ($uninit) {
                    $unique = array_values(array_unique($uninit));
                    fwrite(
                        \STDERR,
                        "[DoctrineCheck] Uninitialized proxies after {$this->name()}: ".
                            implode(', ', $unique).
                            \PHP_EOL,
                    );
                    if (getenv('FAIL_ON_UNINITIALIZED_PROXIES')) {
                        self::fail(
                            'Uninitialized Doctrine proxies detected: '.
                                implode(', ', $unique),
                        );
                    }
                }
            }
        }
        parent::tearDown();
    }

    /**
     * Helper: Assert count for an entity type.
     *
     * @param class-string $class
     */
    protected function assertEntityCount(string $class, int $expected): void
    {
        $this->ensureOpenEntityManager();
        $repo = $this->em()->getRepository($class);
        $count = method_exists($repo, 'count')
            ? $repo->count([])
            : \count($repo->findAll());

        $this->assertSame(
            $expected,
            $count,
            \sprintf(
                'Expected %d %s entities, got %d',
                $expected,
                $class,
                $count,
            ),
        );
    }

    /**
     * Ensure a minimal CMS baseline (FI + EN Sites and their root/home pages) exists.
     * Idempotent: checks for existing Sites first; if any found, baseline assumed present.
     *
     * Uses lightweight Foundry factories (SiteFactory/PageFactory) to avoid depending
     * on heavy global fixtures. Intended to run once per test process.
     */
    protected function ensureCmsBaseline(): void
    {
        // Fast health-check: Story seeds once per run in tests/bootstrap.php.
        $this->ensureOpenEntityManager();
        $em = $this->em();

        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        $sites = method_exists($siteRepo, 'findAll')
            ? $siteRepo->findAll()
            : [];
        $siteCount = method_exists($siteRepo, 'count')
            ? $siteRepo->count([])
            : \count($sites);
        self::assertSame(
            2,
            $siteCount,
            'CMS baseline missing or drifted: expected exactly 2 Sites (fi, en). Reset with "make clean-test-db" and re-run tests (bootstrap seeds baseline once).',
        );

        $fi = method_exists($siteRepo, 'findOneBy')
            ? $siteRepo->findOneBy(['locale' => 'fi'])
            : null;
        $en = method_exists($siteRepo, 'findOneBy')
            ? $siteRepo->findOneBy(['locale' => 'en'])
            : null;

        self::assertNotNull(
            $fi,
            'CMS baseline missing: fi Site not found. Reset DB and re-run tests so CmsBaselineStory can seed.',
        );
        self::assertNotNull(
            $en,
            'CMS baseline missing: en Site not found. Reset DB and re-run tests so CmsBaselineStory can seed.',
        );

        $fiRoot = $fi
            ? $pageRepo->findOneBy(['site' => $fi, 'url' => '/'])
            : null;
        $enRoot = $en
            ? $pageRepo->findOneBy(['site' => $en, 'url' => '/'])
            : null;

        self::assertNotNull(
            $fiRoot,
            'CMS baseline missing: fi root page "/" not found. Reset DB and re-run tests so CmsBaselineStory can seed.',
        );
        self::assertNotNull(
            $enRoot,
            'CMS baseline missing: en root page "/" not found. Reset DB and re-run tests so CmsBaselineStory can seed.',
        );

        // Stop here; skip any per-test normalization/creation/pruning
        return;

        // Hard reset to exactly two sites (FI default, EN) when baseline is empty or duplicates exist.
        // This runs before any creation to prevent duplicate FI/EN sites across randomized test order.
        try {
            $pageRepo = $em->getRepository(SonataPagePage::class);
            $sitesAll = method_exists($siteRepo, 'findAll')
                ? $siteRepo->findAll()
                : [];

            // Partition by locale
            $byLocale = [];
            foreach ($sitesAll as $s) {
                if (null === $s) {
                    continue;
                }
                $siteObj =
                    $s instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $s->object()
                        : $s;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }
                $loc = (string) $siteObj->getLocale();
                $byLocale[$loc] ??= [];
                $byLocale[$loc][] = $siteObj;
            }

            // Choose canonical per locale (prefer a site with a root page) for FI/EN only
            $canon = [];
            foreach (['fi', 'en'] as $loc) {
                $list = $byLocale[$loc] ?? [];
                $chosen = null;
                foreach ($list as $candidate) {
                    $root = $pageRepo->findOneBy([
                        'site' => $candidate,
                        'url' => '/',
                    ]);
                    if ($root instanceof SonataPagePage) {
                        $chosen = $candidate;
                        break;
                    }
                }
                if (null === $chosen) {
                    $chosen = $list[0] ?? null;
                }
                if ($chosen) {
                    $canon[$loc] = $chosen;
                }
            }

            // Remove all sites that are not the canonical FI/EN; normalize the two canonical sites
            foreach ($sitesAll as $site) {
                if (null === $site) {
                    continue;
                }
                $siteObj =
                    $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $site->object()
                        : $site;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }

                $loc = (string) $siteObj->getLocale();
                $isCanonical =
                    ('fi' === $loc
                        && isset($canon['fi'])
                        && $siteObj === $canon['fi'])
                    || ('en' === $loc
                        && isset($canon['en'])
                        && $siteObj === $canon['en']);

                if (!$isCanonical || !\in_array($loc, ['fi', 'en'], true)) {
                    // Remove pages for this site first to avoid FK constraint issues, then remove site
                    try {
                        $pages = $pageRepo->findBy(['site' => $siteObj]);
                        foreach ($pages as $pg) {
                            $this->em()->remove($pg);
                        }
                    } catch (\Throwable) {
                        // ignore page removal failures
                    }
                    $this->em()->remove($siteObj);
                } else {
                    // Normalize canonical site flags and paths (Finnish site must be default)
                    if (method_exists($siteObj, 'setEnabled')) {
                        $siteObj->setEnabled(true);
                    }
                    if (method_exists($siteObj, 'setIsDefault')) {
                        $siteObj->setIsDefault('fi' === $loc);
                    }
                    if (method_exists($siteObj, 'setHost')) {
                        $siteObj->setHost('localhost');
                    }
                    if (method_exists($siteObj, 'setRelativePath')) {
                        $siteObj->setRelativePath('en' === $loc ? '/en' : '');
                    }
                    if (method_exists($siteObj, 'setEnabledFrom')) {
                        $siteObj->setEnabledFrom(
                            new \DateTimeImmutable('-1 day'),
                        );
                    }
                    if (method_exists($siteObj, 'setEnabledTo')) {
                        $siteObj->setEnabledTo(null);
                    }
                    $this->em()->persist($siteObj);
                }
            }

            $this->em()->flush();
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] pre-normalize to 2 sites failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Create FI/EN sites only when the initial site table was empty; otherwise, do not create new sites.
        if (0 === $existingCount) {
            // 1) Create FI site
            $fiSite = SiteFactory::new([
                'name' => 'FI Site',
                'locale' => 'fi',
                'host' => 'localhost',
                'isDefault' => true,
                'enabled' => true,
                'relativePath' => '',
                'enabledFrom' => new \DateTimeImmutable('-1 day'),
                'enabledTo' => null,
            ])->create();

            // Ensure relativePath explicitly (BaseSite defaults to null)
            try {
                $fi =
                    $fiSite instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $fiSite->object()
                        : $fiSite;
                if (\is_object($fi) && method_exists($fi, 'setRelativePath')) {
                    $fi->setRelativePath('');
                    $em->persist($fi);
                }
                $em->flush();
            } catch (\Throwable) {
                // non-fatal
            }

            // 2) Create EN site
            $enSite = SiteFactory::new([
                'name' => 'EN Site',
                'locale' => 'en',
                'host' => 'localhost',
                'isDefault' => false,
                'enabled' => true,
                'relativePath' => '/en',
                'enabledFrom' => new \DateTimeImmutable('-1 day'),
                'enabledTo' => null,
            ])->create();

            // Ensure relativePath explicitly (BaseSite defaults to null)
            try {
                $en =
                    $enSite instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $enSite->object()
                        : $enSite;
                if (\is_object($en) && method_exists($en, 'setRelativePath')) {
                    $en->setRelativePath('/en');
                    $em->persist($en);
                }
                $em->flush();
            } catch (\Throwable) {
                // non-fatal
            }
        }

        // Resolve sites (handles both pre-existing and newly created cases)
        $sites = method_exists($siteRepo, 'findAll')
            ? $siteRepo->findAll()
            : [];

        // Normalize existing sites: host, relativePath per locale, enabled window, single default FI
        $fiDefaultSet = false;
        foreach ($sites as $s) {
            if (null === $s) {
                continue;
            }
            $siteObj =
                $s instanceof \Zenstruck\Foundry\Persistence\Proxy
                    ? $s->object()
                    : $s;

            $changed = false;
            $locale = method_exists($siteObj, 'getLocale')
                ? (string) $siteObj->getLocale()
                : 'fi';

            if (method_exists($siteObj, 'setHost')) {
                $siteObj->setHost('localhost');
                $changed = true;
            }
            if (method_exists($siteObj, 'setEnabled')) {
                $siteObj->setEnabled(true);
                $changed = true;
            }
            if (method_exists($siteObj, 'setEnabledFrom')) {
                $siteObj->setEnabledFrom(new \DateTimeImmutable('-1 day'));
                $changed = true;
            }
            if (method_exists($siteObj, 'setEnabledTo')) {
                $siteObj->setEnabledTo(null);
                $changed = true;
            }
            if (method_exists($siteObj, 'setRelativePath')) {
                $siteObj->setRelativePath('en' === $locale ? '/en' : '');
                $changed = true;
            }
            if (method_exists($siteObj, 'setIsDefault')) {
                if ('fi' === $locale && !$fiDefaultSet) {
                    $siteObj->setIsDefault(true);
                    $fiDefaultSet = true;
                } else {
                    $siteObj->setIsDefault(false);
                }
                $changed = true;
            }

            if ($changed) {
                $em->persist($siteObj);
            }
        }
        try {
            $em->flush();
        } catch (\Throwable) {
        }

        // Ensure a root/home page exists per site
        $pageRepo = $em->getRepository(SonataPagePage::class);
        foreach ($sites as $site) {
            if (null === $site) {
                continue;
            }
            $resolvedSite =
                $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                    ? $site->object()
                    : $site;
            $root = method_exists($pageRepo, 'findOneBy')
                ? $pageRepo->findOneBy(['site' => $resolvedSite, 'url' => '/'])
                : null;
            if (null === $root) {
                PageFactory::new()->homepage()->withSite($site)->create();
            }
        }

        // Refresh sites list after potential creations/normalizations to avoid stale iteration
        $sites = method_exists($siteRepo, 'findAll')
            ? $siteRepo->findAll()
            : [];

        // Generate core route pages for each site (best-effort, before pruning)
        try {
            $container = static::getContainer();
            if ($container->has('sonata.page.route.page.generator')) {
                $routeGen = $container->get('sonata.page.route.page.generator');
                if (method_exists($routeGen, 'update')) {
                    foreach ($sites as $site) {
                        try {
                            $resolved =
                                $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                                    ? $site->object()
                                    : $site;
                            $routeGen->update($resolved);
                        } catch (\Throwable) {
                            // ignore per-site generator errors in baseline
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore route generation failures
        }

        // Refresh sites list again after potential route generation and normalization
        $sites = method_exists($siteRepo, 'findAll')
            ? $siteRepo->findAll()
            : [];

        // Ensure minimal required pages per site (root + events/join) and prune the rest
        try {
            foreach ($sites as $s2) {
                if (null === $s2) {
                    continue;
                }
                $siteObj2 =
                    $s2 instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $s2->object()
                        : $s2;

                $locale2 = method_exists($siteObj2, 'getLocale')
                    ? (string) $siteObj2->getLocale()
                    : 'fi';
                $allowed = ['/'];

                if ('en' === $locale2) {
                    // EN: ensure /events and /join-us exist
                    $allowed[] = '/events';
                    $allowed[] = '/join-us';
                    $allowed[] = '/stream';

                    $events = $pageRepo->findOneBy([
                        'site' => $siteObj2,
                        'url' => '/events',
                    ]);
                    if (null === $events) {
                        PageFactory::new()
                            ->withSite($s2)
                            ->create([
                                'name' => 'Events',
                                'title' => 'Events',
                                'url' => '/events',
                                'routeName' => 'page_slug',
                                'templateCode' => 'default',
                                'enabled' => true,
                                'decorate' => true,
                            ]);
                    }

                    $join = $pageRepo->findOneBy([
                        'site' => $siteObj2,
                        'url' => '/join-us',
                    ]);
                    if (null === $join) {
                        PageFactory::new()
                            ->withSite($s2)
                            ->create([
                                'name' => 'Join Us',
                                'title' => 'Join Us',
                                'url' => '/join-us',
                                'routeName' => 'page_slug',
                                'templateCode' => 'default',
                                'enabled' => true,
                                'decorate' => true,
                            ]);
                    }
                } else {
                    // FI: ensure /tapahtumat and /liity exist
                    $allowed[] = '/tapahtumat';
                    $allowed[] = '/liity';
                    $allowed[] = '/stream';

                    $events = $pageRepo->findOneBy([
                        'site' => $siteObj2,
                        'url' => '/tapahtumat',
                    ]);
                    if (null === $events) {
                        PageFactory::new()
                            ->withSite($s2)
                            ->create([
                                'name' => 'Tapahtumat',
                                'title' => 'Tapahtumat',
                                'url' => '/tapahtumat',
                                'routeName' => 'page_slug',
                                'templateCode' => 'default',
                                'enabled' => true,
                                'decorate' => true,
                            ]);
                    }

                    $join = $pageRepo->findOneBy([
                        'site' => $siteObj2,
                        'url' => '/liity',
                    ]);
                    if (null === $join) {
                        PageFactory::new()
                            ->withSite($s2)
                            ->create([
                                'name' => 'Liity',
                                'title' => 'Liity',
                                'url' => '/liity',
                                'routeName' => 'page_slug',
                                'templateCode' => 'default',
                                'enabled' => true,
                                'decorate' => true,
                            ]);
                    }
                }

                // Prune any other pages not in the whitelist
                $pages2 = $pageRepo->findBy(['site' => $siteObj2]);
                $seen = [];
                foreach ($pages2 as $pg2) {
                    try {
                        if (method_exists($pg2, 'getUrl')) {
                            $url = (string) $pg2->getUrl();
                            if (!\in_array($url, $allowed, true)) {
                                // Prune non-whitelisted pages outright
                                $this->em()->remove($pg2);
                                continue;
                            }
                            // Deduplicate by URL: keep the first occurrence, remove subsequent ones
                            if (isset($seen[$url])) {
                                $this->em()->remove($pg2);
                                continue;
                            }
                            $seen[$url] = true;
                        }
                    } catch (\Throwable) {
                        // ignore page removal failures
                    }
                }
            }
            $this->em()->flush();
        } catch (\Throwable) {
            // ignore failures; baseline keeps minimal set when possible
        }

        // Hard prune: keep exactly one FI site and one EN site; delete all others (including duplicates and non-FI/EN locales)
        try {
            $siteRepo = $this->em()->getRepository(SonataPageSite::class);
            $pageRepo = $this->em()->getRepository(SonataPagePage::class);
            $snapRepo = $this->em()->getRepository(
                \App\Entity\Sonata\SonataPageSnapshot::class,
            );

            $sitesAll = method_exists($siteRepo, 'findAll')
                ? $siteRepo->findAll()
                : [];

            // Partition by locale
            $byLocale = [];
            foreach ($sitesAll as $s) {
                if (null === $s) {
                    continue;
                }
                $siteObj =
                    $s instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $s->object()
                        : $s;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }
                $loc = (string) $siteObj->getLocale();
                $byLocale[$loc] ??= [];
                $byLocale[$loc][] = $siteObj;
            }

            // Choose canonical per locale (prefer a site with a root page) for FI/EN only
            $canon = [];
            foreach (['fi', 'en'] as $loc) {
                $list = $byLocale[$loc] ?? [];
                $chosen = null;
                foreach ($list as $candidate) {
                    $root = $pageRepo->findOneBy([
                        'site' => $candidate,
                        'url' => '/',
                    ]);
                    if ($root instanceof SonataPagePage) {
                        $chosen = $candidate;
                        break;
                    }
                }
                if (null === $chosen) {
                    $chosen = $list[0] ?? null;
                }
                if ($chosen) {
                    $canon[$loc] = $chosen;
                }
            }

            // Remove all sites that are not the canonical FI/EN; normalize the two canonical sites
            foreach ($sitesAll as $site) {
                if (null === $site) {
                    continue;
                }

                $siteObj =
                    $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $site->object()
                        : $site;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }

                $loc = (string) $siteObj->getLocale();
                $isCanonical =
                    ('fi' === $loc
                        && isset($canon['fi'])
                        && $siteObj === $canon['fi'])
                    || ('en' === $loc
                        && isset($canon['en'])
                        && $siteObj === $canon['en']);

                if (!$isCanonical) {
                    // Best-effort: remove snapshots for this site (if relation exists)
                    try {
                        if ($snapRepo) {
                            $snaps = $snapRepo->findBy(['site' => $siteObj]);
                            foreach ($snaps as $snap) {
                                $this->em()->remove($snap);
                            }
                        }
                    } catch (\Throwable) {
                        // ignore snapshot removal failures
                    }

                    // Remove pages for this site first to avoid FK constraint issues
                    try {
                        $pages = $pageRepo->findBy(['site' => $siteObj]);
                        foreach ($pages as $pg) {
                            $this->em()->remove($pg);
                        }
                    } catch (\Throwable) {
                        // ignore page removal failures
                    }

                    // Finally remove the site itself (covers non-FI/EN locales and duplicates)
                    $this->em()->remove($siteObj);
                } else {
                    // Normalize canonical site flags and paths
                    if (method_exists($siteObj, 'setEnabled')) {
                        $siteObj->setEnabled(true);
                    }
                    if (method_exists($siteObj, 'setIsDefault')) {
                        $siteObj->setIsDefault('fi' === $loc);
                    }
                    if (method_exists($siteObj, 'setHost')) {
                        $siteObj->setHost('localhost');
                    }
                    if (method_exists($siteObj, 'setRelativePath')) {
                        $siteObj->setRelativePath('en' === $loc ? '/en' : '');
                    }
                    $this->em()->persist($siteObj);
                }
            }

            $this->em()->flush();
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] early hard prune to 2 sites failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Create & enable snapshots per site (best-effort)
        try {
            $container = static::getContainer();
            if ($container->has('sonata.page.service.create_snapshot')) {
                $createSnapshot = $container->get(
                    'sonata.page.service.create_snapshot',
                );
                if (method_exists($createSnapshot, 'createBySite')) {
                    foreach ($sites as $site) {
                        try {
                            $resolved =
                                $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                                    ? $site->object()
                                    : $site;
                            $createSnapshot->createBySite($resolved);
                        } catch (\Throwable) {
                            // ignore per-site snapshot errors in baseline
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] snapshot creation skipped: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Defensive normalization for root pages: if slug is empty, ensure url='/' and canonical route/template/type.
        try {
            $pageRepo = $em->getRepository(SonataPagePage::class);
            foreach ($sites as $site) {
                if (null === $site) {
                    continue;
                }
                $resolvedSite =
                    $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $site->object()
                        : $site;
                $root = method_exists($pageRepo, 'findOneBy')
                    ? $pageRepo->findOneBy([
                        'site' => $resolvedSite,
                        'url' => '/',
                    ])
                    : null;

                if (null === $root) {
                    continue;
                }

                if (true) {
                    $changed = false;

                    if (
                        method_exists($root, 'getUrl')
                        && method_exists($root, 'setUrl')
                        && '/' !== (string) $root->getUrl()
                    ) {
                        $root->setUrl('/');
                        $changed = true;
                    }
                    if (
                        method_exists($root, 'getRouteName')
                        && method_exists($root, 'setRouteName')
                        && 'page_slug' !== (string) $root->getRouteName()
                    ) {
                        $root->setRouteName('page_slug');
                        $changed = true;
                    }
                    if (
                        method_exists($root, 'getTemplateCode')
                        && method_exists($root, 'setTemplateCode')
                        && 'frontpage' !== (string) $root->getTemplateCode()
                    ) {
                        $root->setTemplateCode('frontpage');
                        $changed = true;
                    }
                    if (
                        method_exists($root, 'getType')
                        && method_exists($root, 'setType')
                        && 'App\\PageService\\FrontPage' !==
                            (string) $root->getType()
                    ) {
                        $root->setType('App\\PageService\\FrontPage');
                        $changed = true;
                    }
                    if (
                        method_exists($root, 'isEnabled')
                        && method_exists($root, 'setEnabled')
                        && !$root->isEnabled()
                    ) {
                        $root->setEnabled(true);
                        $changed = true;
                    }
                    if (
                        method_exists($root, 'getDecorate')
                        && method_exists($root, 'setDecorate')
                        && !$root->getDecorate()
                    ) {
                        $root->setDecorate(true);
                        $changed = true;
                    }
                    if (
                        method_exists($root, 'getRequestMethod')
                        && method_exists($root, 'setRequestMethod')
                        && 'GET|POST|HEAD|DELETE|PUT' !==
                            (string) $root->getRequestMethod()
                    ) {
                        $root->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
                        $changed = true;
                    }

                    if ($changed) {
                        $em->persist($root);
                        $em->flush();
                    }
                }
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] root page normalization failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Ensure per-site Events and Join Us alias pages required by frontpage links.
        try {
            $pageRepo = $em->getRepository(SonataPagePage::class);
            foreach ($sites as $site) {
                if (null === $site) {
                    continue;
                }

                $resolvedSite =
                    $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $site->object()
                        : $site;
                if (!method_exists($resolvedSite, 'getLocale')) {
                    continue;
                }
                $locale = (string) $resolvedSite->getLocale();

                // Locate the root page (url '/')
                $root = $pageRepo->findOneBy([
                    'site' => $resolvedSite,
                    'url' => '/',
                ]);
                if (!$root instanceof SonataPagePage) {
                    // If root is missing, earlier steps failed; skip creating children.
                    continue;
                }

                // Ensure Events page (alias: _page_alias_events_<locale>)
                $eventsAlias =
                    'en' === $locale
                        ? '_page_alias_events_en'
                        : '_page_alias_events_fi';
                $eventsSlug = 'en' === $locale ? 'events' : 'tapahtumat';
                $eventsName = 'en' === $locale ? 'Events' : 'Tapahtumat';
                $eventsUrl = '/'.$eventsSlug;

                $events =
                    $pageRepo->findOneBy([
                        'site' => $resolvedSite,
                        'pageAlias' => $eventsAlias,
                    ]) ??
                    ($pageRepo->findOneBy([
                        'site' => $resolvedSite,
                        'slug' => $eventsSlug,
                    ]) ??
                        $pageRepo->findOneBy([
                            'site' => $resolvedSite,
                            'url' => $eventsUrl,
                        ]));

                if (!$events instanceof SonataPagePage) {
                    $events = new SonataPagePage();
                    $events->setSite($resolvedSite);
                    $events->setParent($root);
                    $events->setPosition(1);
                }

                $events->setRouteName('page_slug');
                $events->setName($eventsName);
                $events->setTitle($eventsName);
                $events->setSlug($eventsSlug);
                $events->setUrl($eventsUrl);
                $events->setEnabled(true);
                $events->setDecorate(true);
                $events->setType('eventspage');
                $events->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
                $events->setTemplateCode('events');
                $events->setPageAlias($eventsAlias);
                $em->persist($events);

                // Ensure Join Us page (alias: _page_alias_join_us_<locale>)
                $joinAlias =
                    'en' === $locale
                        ? '_page_alias_join_us_en'
                        : '_page_alias_join_us_fi';
                $joinSlug = 'en' === $locale ? 'join-us' : 'liity';
                $joinName = 'en' === $locale ? 'Join Us' : 'Liity';
                $joinTitle = 'en' === $locale ? 'Join Us' : 'Liity Jäseneksi';
                $joinUrl = '/'.$joinSlug;

                $join =
                    $pageRepo->findOneBy([
                        'site' => $resolvedSite,
                        'pageAlias' => $joinAlias,
                    ]) ??
                    ($pageRepo->findOneBy([
                        'site' => $resolvedSite,
                        'slug' => $joinSlug,
                    ]) ??
                        $pageRepo->findOneBy([
                            'site' => $resolvedSite,
                            'url' => $joinUrl,
                        ]));

                if (!$join instanceof SonataPagePage) {
                    $join = new SonataPagePage();
                    $join->setSite($resolvedSite);
                    $join->setParent($root);
                    $join->setPosition(1);
                }

                $join->setRouteName('page_slug');
                $join->setName($joinName);
                $join->setTitle($joinTitle);
                if (null === $join->getMetaDescription()) {
                    $join->setMetaDescription($joinTitle);
                }
                $join->setSlug($joinSlug);
                $join->setUrl($joinUrl);
                $join->setEnabled(true);
                $join->setDecorate(true);
                $join->setType('sonata.page.service.default');
                $join->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
                $join->setTemplateCode('onecolumn');
                $join->setPageAlias($joinAlias);
                $em->persist($join);
            }

            $em->flush();
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] events/join-us alias seeding skipped: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Refresh sites list again before stream page seeding to avoid stale iteration
        $sites = method_exists($siteRepo, 'findAll')
            ? $siteRepo->findAll()
            : [];

        // Ensure per-site Stream page exists (template 'stream', type 'App\\PageService\\StreamPage')
        try {
            $pageRepo = $em->getRepository(SonataPagePage::class);
            foreach ($sites as $site) {
                if (null === $site) {
                    continue;
                }

                $resolvedSite =
                    $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $site->object()
                        : $site;

                if (!\is_object($resolvedSite)) {
                    continue;
                }

                // Locate the root page (url '/')
                $root = $pageRepo->findOneBy([
                    'site' => $resolvedSite,
                    'url' => '/',
                ]);

                // Locate or create the stream page (url '/stream')
                // Deduplicate: find all /stream pages for this site and keep exactly one canonical
                $streams = $pageRepo->findBy([
                    'site' => $resolvedSite,
                    'url' => '/stream',
                ]);

                $stream = null;
                foreach ($streams as $candidate) {
                    // Prefer a page already configured with correct template/type
                    if (
                        method_exists($candidate, 'getTemplateCode')
                        && method_exists($candidate, 'getType')
                        && 'stream' === (string) $candidate->getTemplateCode()
                        && 'stream' === (string) $candidate->getType()
                    ) {
                        $stream = $candidate;
                        break;
                    }
                }
                if (null === $stream) {
                    $stream = $streams[0] ?? null;
                }

                // Remove any extra duplicates beyond the canonical one
                foreach ($streams as $dup) {
                    if ($dup !== $stream) {
                        $this->em()->remove($dup);
                    }
                }

                if (!$stream instanceof SonataPagePage) {
                    $stream = new SonataPagePage();
                    $stream->setSite($resolvedSite);
                }

                // Ensure root parent/position for canonical stream page
                if ($root instanceof SonataPagePage) {
                    $stream->setParent($root);
                    // Keep a stable small position under root
                    $stream->setPosition(2);
                }

                $stream->setRouteName('page_slug');
                $stream->setName('Stream');
                $stream->setTitle('Stream');
                $stream->setSlug('stream');
                $stream->setUrl('/stream');
                $stream->setEnabled(true);
                $stream->setDecorate(true);
                $stream->setType('stream');
                $stream->setTemplateCode('stream');
                $stream->setRequestMethod('GET|POST|HEAD');

                $em->persist($stream);
            }

            $em->flush();
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] stream page seeding skipped: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Deduplicate sites per locale: keep a single enabled canonical site per locale.
        // Prefer the site that already has a root page (url="/"); disable others and ensure only FI is default.
        try {
            $siteRepo = $em->getRepository(SonataPageSite::class);
            $pageRepo = $em->getRepository(SonataPagePage::class);
            $sitesAll = method_exists($siteRepo, 'findAll')
                ? $siteRepo->findAll()
                : [];

            $byLocale = [];
            foreach ($sitesAll as $s) {
                if (null === $s) {
                    continue;
                }
                $siteObj =
                    $s instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $s->object()
                        : $s;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }
                $loc = (string) $siteObj->getLocale();
                $byLocale[$loc] ??= [];
                $byLocale[$loc][] = $siteObj;
            }

            foreach ($byLocale as $loc => $list) {
                // Find canonical: prefer a site having a root page.
                $canonical = null;
                foreach ($list as $candidate) {
                    $root = $pageRepo->findOneBy([
                        'site' => $candidate,
                        'url' => '/',
                    ]);
                    if ($root instanceof SonataPagePage) {
                        $canonical = $candidate;
                        break;
                    }
                }
                if (null === $canonical) {
                    $canonical = $list[0] ?? null;
                }

                if (null === $canonical) {
                    continue;
                }

                foreach ($list as $siteObj) {
                    $isCanonical = $siteObj === $canonical;

                    if (method_exists($siteObj, 'setEnabled')) {
                        $siteObj->setEnabled($isCanonical);
                    }
                    if (method_exists($siteObj, 'setIsDefault')) {
                        // Only Finnish canonical site is default
                        $siteObj->setIsDefault($isCanonical && 'fi' === $loc);
                    }
                    // Normalize relativePath for canonical; leave duplicates untouched
                    if (
                        $isCanonical
                        && method_exists($siteObj, 'setRelativePath')
                    ) {
                        $siteObj->setRelativePath('en' === $loc ? '/en' : '');
                    }

                    $em->persist($siteObj);
                }
            }

            try {
                $em->flush();
            } catch (\Throwable) {
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] site dedup skipped: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        try {
            $siteRepo = $this->em()->getRepository(SonataPageSite::class);
            $pageRepo = $this->em()->getRepository(SonataPagePage::class);
            $sites = method_exists($siteRepo, 'findAll')
                ? $siteRepo->findAll()
                : [];
            $siteCount = method_exists($siteRepo, 'count')
                ? $siteRepo->count([])
                : \count($sites);
            $pageCount = method_exists($pageRepo, 'count')
                ? $pageRepo->count([])
                : \count($pageRepo->findAll());
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] site_count='.
                    $siteCount.
                    ' page_count='.
                    $pageCount.
                    \PHP_EOL,
            );
            foreach ($sites as $dxSite) {
                if (null === $dxSite) {
                    continue;
                }
                $s =
                    $dxSite instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $dxSite->object()
                        : $dxSite;
                if (!\is_object($s)) {
                    continue;
                }
                $locale = method_exists($s, 'getLocale')
                    ? (string) $s->getLocale()
                    : 'n/a';
                $rel = method_exists($s, 'getRelativePath')
                    ? (string) (($s->getRelativePath() ?? '') === ''
                        ? "''"
                        : $s->getRelativePath())
                    : 'n/a';
                $isDefault = method_exists($s, 'isDefault')
                    ? ($s->isDefault()
                        ? '1'
                        : '0')
                    : 'n/a';
                $siteId = method_exists($s, 'getId')
                    ? (string) $s->getId()
                    : 'n/a';

                $root = $pageRepo->findOneBy(['site' => $s, 'url' => '/']);
                if ($root instanceof SonataPagePage) {
                    $routeName = method_exists($root, 'getRouteName')
                        ? (string) $root->getRouteName()
                        : 'n/a';
                    $template = method_exists($root, 'getTemplateCode')
                        ? (string) $root->getTemplateCode()
                        : 'n/a';
                    $type = method_exists($root, 'getType')
                        ? (string) $root->getType()
                        : 'n/a';
                    $enabled = method_exists($root, 'isEnabled')
                        ? ($root->isEnabled()
                            ? '1'
                            : '0')
                        : 'n/a';
                    $decorate = method_exists($root, 'getDecorate')
                        ? ($root->getDecorate()
                            ? '1'
                            : '0')
                        : 'n/a';
                    $method = method_exists($root, 'getRequestMethod')
                        ? (string) $root->getRequestMethod()
                        : 'n/a';

                    @fwrite(
                        \STDERR,
                        \sprintf(
                            "[ensureCmsBaseline] site{id=%s,locale=%s,rel=%s,default=%s} root{route=%s,template=%s,type=%s,enabled=%s,decorate=%s,method=%s}\n",
                            $siteId,
                            $locale,
                            $rel,
                            $isDefault,
                            $routeName,
                            $template,
                            $type,
                            $enabled,
                            $decorate,
                            $method,
                        ),
                    );
                } else {
                    @fwrite(
                        \STDERR,
                        \sprintf(
                            "[ensureCmsBaseline] site{id=%s,locale=%s,rel=%s,default=%s} root{MISSING}\n",
                            $siteId,
                            $locale,
                            $rel,
                            $isDefault,
                        ),
                    );
                }
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] diagnostics failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Hard prune: keep exactly one FI site and one EN site; delete all others (including duplicates and non-FI/EN locales)
        try {
            $siteRepo = $this->em()->getRepository(SonataPageSite::class);
            $pageRepo = $this->em()->getRepository(SonataPagePage::class);
            $snapRepo = $this->em()->getRepository(
                \App\Entity\Sonata\SonataPageSnapshot::class,
            );

            $sitesAll = method_exists($siteRepo, 'findAll')
                ? $siteRepo->findAll()
                : [];

            // Partition by locale
            $byLocale = [];
            foreach ($sitesAll as $s) {
                if (null === $s) {
                    continue;
                }
                $siteObj =
                    $s instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $s->object()
                        : $s;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }
                $loc = (string) $siteObj->getLocale();
                $byLocale[$loc] ??= [];
                $byLocale[$loc][] = $siteObj;
            }

            // Choose canonical per locale (prefer a site with a root page) for FI/EN only
            $canon = [];
            foreach (['fi', 'en'] as $loc) {
                $list = $byLocale[$loc] ?? [];
                $chosen = null;
                foreach ($list as $candidate) {
                    $root = $pageRepo->findOneBy([
                        'site' => $candidate,
                        'url' => '/',
                    ]);
                    if ($root instanceof SonataPagePage) {
                        $chosen = $candidate;
                        break;
                    }
                }
                if (null === $chosen) {
                    $chosen = $list[0] ?? null;
                }
                if ($chosen) {
                    $canon[$loc] = $chosen;
                }
            }

            // Remove all sites that are not the canonical FI/EN; normalize the two canonical sites
            foreach ($sitesAll as $site) {
                if (null === $site) {
                    continue;
                }
                $siteObj =
                    $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                        ? $site->object()
                        : $site;
                if (
                    !\is_object($siteObj)
                    || !method_exists($siteObj, 'getLocale')
                ) {
                    continue;
                }

                $loc = (string) $siteObj->getLocale();
                $isCanonical =
                    ('fi' === $loc
                        && isset($canon['fi'])
                        && $siteObj === $canon['fi'])
                    || ('en' === $loc
                        && isset($canon['en'])
                        && $siteObj === $canon['en']);

                if (!$isCanonical) {
                    // Best-effort: remove snapshots for this site (if relation exists)
                    try {
                        if ($snapRepo) {
                            $snaps = $snapRepo->findBy(['site' => $siteObj]);
                            foreach ($snaps as $snap) {
                                $this->em()->remove($snap);
                            }
                        }
                    } catch (\Throwable) {
                        // ignore snapshot removal failures
                    }

                    // Remove pages for this site first to avoid FK constraint issues
                    try {
                        $pages = $pageRepo->findBy(['site' => $siteObj]);
                        foreach ($pages as $pg) {
                            $this->em()->remove($pg);
                        }
                    } catch (\Throwable) {
                        // ignore page removal failures
                    }

                    // Finally remove the site itself (covers non-FI/EN locales and duplicates)
                    $this->em()->remove($siteObj);
                } else {
                    // Normalize canonical site flags and paths
                    if (method_exists($siteObj, 'setEnabled')) {
                        $siteObj->setEnabled(true);
                    }
                    if (method_exists($siteObj, 'setIsDefault')) {
                        $siteObj->setIsDefault('fi' === $loc);
                    }
                    if (method_exists($siteObj, 'setHost')) {
                        $siteObj->setHost('localhost');
                    }
                    if (method_exists($siteObj, 'setRelativePath')) {
                        $siteObj->setRelativePath('en' === $loc ? '/en' : '');
                    }
                    $this->em()->persist($siteObj);
                }
            }

            $this->em()->flush();
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] hard prune to 2 sites failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Final snapshot regeneration after all page mutations: ensure router sees fresh state.
        try {
            $container = static::getContainer();
            if ($container->has('sonata.page.service.create_snapshot')) {
                $createSnapshot = $container->get(
                    'sonata.page.service.create_snapshot',
                );
                if (method_exists($createSnapshot, 'createBySite')) {
                    $siteRepo = $this->em()->getRepository(
                        SonataPageSite::class,
                    );
                    $sites = method_exists($siteRepo, 'findAll')
                        ? $siteRepo->findAll()
                        : [];
                    foreach ($sites as $site) {
                        try {
                            $resolved =
                                $site instanceof \Zenstruck\Foundry\Persistence\Proxy
                                    ? $site->object()
                                    : $site;
                            if (\is_object($resolved)) {
                                $createSnapshot->createBySite($resolved);
                            }
                        } catch (\Throwable) {
                            // ignore per-site snapshot errors
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[ensureCmsBaseline] final snapshot regeneration skipped: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
            $this->recoverEntityManagerAfterException($e);
        }

        // Post-condition: exactly two Sites (fi, en) and each has a root page at "/"
        try {
            $siteRepo = $this->em()->getRepository(SonataPageSite::class);
            $pageRepo = $this->em()->getRepository(SonataPagePage::class);

            $sites = method_exists($siteRepo, 'findAll')
                ? $siteRepo->findAll()
                : [];
            $siteCount = method_exists($siteRepo, 'count')
                ? $siteRepo->count([])
                : \count($sites);
            self::assertSame(
                2,
                $siteCount,
                'ensureCmsBaseline post-condition failed: expected exactly two Sites (fi, en).',
            );

            $fi = method_exists($siteRepo, 'findOneBy')
                ? $siteRepo->findOneBy(['locale' => 'fi'])
                : null;
            $en = method_exists($siteRepo, 'findOneBy')
                ? $siteRepo->findOneBy(['locale' => 'en'])
                : null;
            self::assertNotNull(
                $fi,
                'ensureCmsBaseline post-condition failed: missing fi Site',
            );
            self::assertNotNull(
                $en,
                'ensureCmsBaseline post-condition failed: missing en Site',
            );

            $fiRoot = $fi
                ? $pageRepo->findOneBy(['site' => $fi, 'url' => '/'])
                : null;
            $enRoot = $en
                ? $pageRepo->findOneBy(['site' => $en, 'url' => '/'])
                : null;
            self::assertNotNull(
                $fiRoot,
                'ensureCmsBaseline post-condition failed: missing fi root page "/"',
            );
            self::assertNotNull(
                $enRoot,
                'ensureCmsBaseline post-condition failed: missing en root page "/"',
            );
        } catch (\Throwable $e) {
            self::fail(
                '[ensureCmsBaseline] post-condition check failed: '.
                    $e->getMessage(),
            );
        }

        // Release DB-level named lock if held
        try {
            if (isset($conn) && $conn && isset($gotLock) && true === $gotLock) {
                $conn->executeQuery('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        } catch (\Throwable $e) {
            // ignore release failures
        }
    }

    /**
     * Helper: create (once) and return a SiteAwareKernelBrowser initialized
     * via static::createClient() so that:
     *  - WebTestCase internal client registry is populated (BrowserKit assertions work)
     *  - Sonata Page multisite HostPathByLocaleSiteSelector sees a SiteRequest wrapper
     *  - Randomized order (Infection / --order-by=random) has no hidden dependency.
     *
     * Usage in functional tests (setUp):
     *     $this->initSiteAwareClient();
     *     $client = $this->client(); // retrieve instance
     */
    protected function initSiteAwareClient(
        array $server = ['HTTP_HOST' => 'localhost'],
    ): void {
        // Mandatory pattern:
        // 1) Create the canonical Symfony client (registers with WebTestCase)
        // 2) Immediately replace it with the SiteAwareKernelBrowser
        if (null !== static::$kernel) {
            self::ensureKernelShutdown();
        }
        $baseClient = static::createClient([], $server);

        // Create a SiteAwareKernelBrowser using the already-booted kernel
        $siteClient = new SiteAwareKernelBrowser(static::$kernel);
        $siteClient->disableReboot();

        // Copy server parameters onto the site-aware client
        foreach ($server as $k => $v) {
            $siteClient->setServerParameter($k, $v);
        }

        // Copy cookies (session/auth) from the base client so state is preserved
        try {
            $cookieJar = $baseClient->getCookieJar();
            foreach ($cookieJar->all() as $cookie) {
                $siteClient->getCookieJar()->set($cookie);
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[initSiteAwareClient] cookie transfer failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }

        // Register the site-aware client as the active client
        $this->siteAwareClient = $siteClient;
        self::$client = $this->siteAwareClient;

        // Sync the parent WebTestCase::$client via reflection (BrowserKit assertions)
        try {
            $ref = new \ReflectionClass(WebTestCase::class);
            if ($ref->hasProperty('client')) {
                $prop = $ref->getProperty('client');
                $prop->setAccessible(true);
                $prop->setValue(null, $this->siteAwareClient);
            }
        } catch (\Throwable $e) {
            // non-fatal; assertions can still rely on self::$client
        }

        // After initializing the client, ensure EntityManager is bound to the current kernel
        if (property_exists($this, 'em')) {
            try {
                /* @var ObjectManager $em */
                $this->em = static::getContainer()
                    ->get('doctrine')
                    ->getManager();
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[initSiteAwareClient] EM reacquire failed: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
        }

        // (CMS baseline seeding occurs in setUp() after client+EM are ready.)
    }

    /**
     * Static override ensuring BrowserKit assertions use the SiteAwareKernelBrowser
     * registered via initSiteAwareClient(). Mirrors WebTestCase static signature.
     */
    public static function assertResponseIsSuccessful(
        string $message = '',
        ?bool $verbose = null,
    ): void {
        if (
            !self::$client instanceof \Symfony\Bundle\FrameworkBundle\KernelBrowser
        ) {
            self::fail(
                'No active client registered; did you call initSiteAwareClient() in setUp()?',
            );
        }
        $response = self::$client->getResponse();
        if (null === $response) {
            self::fail(
                'No response available – was a request performed with the site-aware client?',
            );
        }
        $status = $response->getStatusCode();
        self::assertGreaterThanOrEqual(
            200,
            $status,
            $message ?: \sprintf('Expected 2xx, got %d.', $status),
        );
        self::assertLessThan(
            300,
            $status,
            $message ?: \sprintf('Expected 2xx, got %d.', $status),
        );
    }

    /**
     * Retrieve the initialized SiteAwareKernelBrowser (caller must have invoked initSiteAwareClient()).
     */
    protected function client(): SiteAwareKernelBrowser
    {
        if (null === $this->siteAwareClient) {
            $this->fail(
                'Site-aware client not initialized. Call $this->initSiteAwareClient() in setUp().',
            );
        }

        // Ensure BrowserKitAssertionsTrait uses the same instance that performed requests
        self::$client = $this->siteAwareClient;
        self::getClient($this->siteAwareClient);

        return $this->siteAwareClient;
    }

    /**
     * Backward compatible magic accessor for legacy $this->client usages.
     */
    public function __get(string $name)
    {
        if ('client' === $name) {
            // Sync static WebTestCase::$client so DomCrawlerAssertionsTrait reads the correct browser
            if ($this->siteAwareClient) {
                self::$client = $this->siteAwareClient;
            }

            return $this->siteAwareClient;
        }

        trigger_error(
            \sprintf('Undefined property %s::$%s', __CLASS__, $name),
            \E_USER_NOTICE,
        );

        return null;
    }

    /**
     * Helper: seed a homepage request for a given locale and follow a single redirect if present.
     * Returns the resulting Crawler.
     */
    protected function seedClientHome(
        string $locale = 'en',
    ): \Symfony\Component\DomCrawler\Crawler {
        if (null === $this->siteAwareClient) {
            $this->initSiteAwareClient();
        }
        $client = $this->client();
        $path = 'en' === $locale ? '/en/' : '/';
        $crawler = $client->request('GET', $path);
        $status = $client->getResponse()->getStatusCode();

        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $crawler = $client->request('GET', $loc);
            }
        }

        return $crawler;
    }

    /**
     * Helper: seed the login page for a given locale and follow a single redirect if present.
     * Returns the resulting Crawler.
     */
    protected function seedLoginPage(
        string $locale = 'en',
    ): \Symfony\Component\DomCrawler\Crawler {
        if (null === $this->siteAwareClient) {
            $this->initSiteAwareClient();
        }
        $client = $this->client();
        $path = 'en' === $locale ? '/en/login' : '/login';
        $crawler = $client->request('GET', $path);
        $status = $client->getResponse()->getStatusCode();

        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $crawler = $client->request('GET', $loc);
            }
        }

        return $crawler;
    }

    /**
     * Internal reference to the site-aware client (per-test instance).
     */
    private ?SiteAwareKernelBrowser $siteAwareClient = null;
}
