<?php

declare(strict_types=1);

namespace App\Tests\_Base;

use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
 *
 * Explicitly removed helpers:
 *  - reloadFixtures(): now throws LogicException (tests should not call)
 */
abstract class FixturesWebTestCase extends WebTestCase
{
    protected static ?ObjectManager $em = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === self::$em) {
            /** @var ObjectManager $em */
            $em = static::getContainer()->get('doctrine')->getManager();
            self::$em = $em;
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
        if (null === self::$em) {
            self::fail('EntityManager not initialized.');
        }

        return self::$em;
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
        $repo = $this->em()->getRepository($class);
        $entity = $repo->findOneBy($criteria);
        $this->assertNotNull(
            $entity,
            sprintf(
                'Expected one %s for criteria %s, got none.',
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
        $count = method_exists($repo, 'count')
            ? $repo->count([])
            : count($repo->findAll());

        $this->assertSame(
            $expected,
            $count,
            sprintf(
                'Expected %d %s entities, got %d',
                $expected,
                $class,
                $count,
            ),
        );
    }
}
