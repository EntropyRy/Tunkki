<?php

declare(strict_types=1);

namespace App\Tests\_Base;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base test case that:
 *  - Boots the Symfony kernel once per PHP process (via WebTestCase)
 *  - Purges the database only once (before first test using this base)
 *  - Loads a canonical core fixture set (Site, User, Event fixtures)
 *
 * Extend this in Functional / Integration tests to guarantee a known baseline:
 *
 *   namespace App\Tests\Functional;
 *   use App\Tests\_Base\FixturesWebTestCase;
 *
 *   final class SomeFeatureTest extends FixturesWebTestCase { ... }
 *
 * To customize fixtures for a specific test class:
 *   - Override getFixtureClasses() and return an ordered array of class names.
 *   - If you need completely isolated data per test method, call
 *       $this->reloadFixtures()
 *     at the start of the test (note: slower).
 */
abstract class FixturesWebTestCase extends WebTestCase
{
    /** @var ObjectManager|null */
    protected static ?ObjectManager $em = null;

    /** @var bool */
    private static bool $coreFixturesLoaded = false;

    /**
     * Override to provide a custom (ordered) list of fixture FQCNs.
     * If you need constructor arguments (e.g., password hasher), this base
     * will detect and inject for known fixtures (UserFixtures).
     *
     * @return list<class-string>
     */
    protected function getFixtureClasses(): array
    {
        $defaults = [
            \App\DataFixtures\SiteFixtures::class,
            \App\DataFixtures\UserFixtures::class,
            \App\DataFixtures\ArtistFixtures::class,
            \App\DataFixtures\EventFixtures::class,
        ];

        // Fail early if any required fixture is missing
        foreach ($defaults as $fqcn) {
            if (!class_exists($fqcn)) {
                self::fail("Required fixture class not found: {$fqcn}");
            }
        }

        return $defaults;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (null === self::$em) {
            /** @var ObjectManager $em */
            $em = static::getContainer()->get("doctrine")->getManager();
            self::$em = $em;
        }

        // Load fixtures once per test process (speed optimization)
        if (!self::$coreFixturesLoaded) {
            $this->purgeDatabase();
            $this->loadCoreFixtures();
            self::$coreFixturesLoaded = true;
        }
    }

    /**
     * Explicitly reload all fixtures (slower). Call this inside a test if you
     * need a pristine DB state different from previous tests.
     */
    protected function reloadFixtures(): void
    {
        $this->purgeDatabase();
        $this->loadCoreFixtures();
    }

    /**
     * Expose the EntityManager to child tests with a null check.
     */
    protected function em(): ObjectManager
    {
        if (null === self::$em) {
            self::fail("EntityManager not initialized.");
        }
        return self::$em;
    }

    /**
     * Purge using DELETE mode (safer for FK constraints than TRUNCATE on some platforms).
     */
    private function purgeDatabase(): void
    {
        $em = $this->em();
        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $executor = new ORMExecutor($em, $purger);
        $executor->purge();
    }

    /**
     * Load the configured fixtures.
     */
    private function loadCoreFixtures(): void
    {
        $em = $this->em();
        $loader = new Loader();

        foreach ($this->getFixtureClasses() as $fixtureClass) {
            // Handle known constructor dependencies
            if ($fixtureClass === \App\DataFixtures\UserFixtures::class) {
                $loader->addFixture(
                    new \App\DataFixtures\UserFixtures(
                        static::getContainer()->get(
                            \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class,
                        ),
                    ),
                );
                continue;
            }

            $loader->addFixture(new $fixtureClass());
        }

        $executor = new ORMExecutor($em, new ORMPurger($em));
        $executor->execute($loader->getFixtures(), true);
    }

    /**
     * Helper: Fetch an entity by class & criteria with assertions.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $criteria
     * @return T
     */
    protected function findOneOrFail(string $class, array $criteria): object
    {
        $repo = $this->em()->getRepository($class);
        $entity = $repo->findOneBy($criteria);
        $this->assertNotNull(
            $entity,
            sprintf(
                "Expected one %s for criteria %s, got none.",
                $class,
                json_encode($criteria, JSON_THROW_ON_ERROR),
            ),
        );
        return $entity;
    }

    /**
     * Helper: Assert count for an entity type.
     *
     * @param class-string $class
     */
    protected function assertEntityCount(string $class, int $expected): void
    {
        $repo = $this->em()->getRepository($class);
        $count = method_exists($repo, "count")
            ? $repo->count([])
            : count($repo->findAll());

        $this->assertSame(
            $expected,
            $count,
            sprintf(
                "Expected %d %s entities, got %d",
                $expected,
                $class,
                $count,
            ),
        );
    }
}
