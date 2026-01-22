<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Tests\_Base\FixturesWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Zenstruck\Foundry\Test\Factories;

/**
 * Base class for Doctrine repository integration tests.
 *
 * This simplified version intentionally avoids per‑test database transactions.
 * Rationale:
 *  - Global canonical fixtures are loaded once by the CI bootstrap script (test.sh).
 *  - The previous pattern (beginTransaction / rollBack on every test) introduced
 *    stale proxies & closed EntityManager issues when exceptions occurred mid‑test,
 *    cascading into dozens of "EntityManagerClosed" errors in later tests.
 *  - Clearing the EntityManager between tests is sufficient to prevent in‑memory
 *    cross‑test state leakage while keeping fixture rows available.
 *
 * Guidelines for repository tests:
 *  - You may freely persist additional entities; they will remain for later tests.
 *    If a test creates large volumes of data that could skew other assertions,
 *    perform explicit clean‑up or prefer more selective creation.
 *  - Do NOT modify or delete core/global fixture records (Sites, root Pages, etc.).
 *  - If a specific test must assert post‑commit side effects (e.g. DB triggers),
 *    no special handling is required—writes are committed immediately.
 *
 * Provided helpers:
 *  - repo($class)                       : typed repository fetch
 *  - persistAndFlush($entity|iterable)  : convenience persister
 *  - assertEntityCountByCriteria(...)   : criteria count assertion
 *  - refresh($entity)                   : refresh state from DB
 *  - em()                               : typed EntityManager accessor
 */
abstract class RepositoryTestCase extends FixturesWebTestCase
{
    use Factories;

    /**
     * setUp: just call parent and clear EM to ensure no stale managed entities
     * linger from previous tests (functional tests may keep references).
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Defensive clear: if a previous functional test left detached / proxy
        // references around, this guarantees a clean identity map.
        $this->em()->clear();
    }

    protected function tearDown(): void
    {
        // Clear again so that any entities created in this test do not remain
        // in managed state for the next test's identity map expectations.
        $this->em()->clear();
        parent::tearDown();
    }

    /**
     * Shortcut: obtain repository for an entity class.
     *
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return ObjectRepository<T>
     */
    protected function repo(string $entityClass): ObjectRepository
    {
        return $this->em()->getRepository($entityClass);
    }

    /**
     * Persist one or more entities and flush.
     *
     * @param object|iterable<object> $entities
     */
    protected function persistAndFlush(object|iterable $entities): void
    {
        $em = $this->em();
        if (is_iterable($entities)) {
            foreach ($entities as $entity) {
                $em->persist($entity);
            }
        } else {
            $em->persist($entities);
        }
        $em->flush();
    }

    /**
     * Assert an entity count by criteria (uses repository ->count() if available).
     *
     * @template T of object
     *
     * @param class-string<T>     $entityClass
     * @param array<string,mixed> $criteria
     */
    protected function assertEntityCountByCriteria(
        string $entityClass,
        array $criteria,
        int $expected,
    ): void {
        $repo = $this->repo($entityClass);
        $actual = method_exists($repo, 'count')
            ? $repo->count($criteria)
            : \count($repo->findBy($criteria));

        self::assertSame(
            $expected,
            $actual,
            \sprintf(
                'Expected %d %s entities for criteria %s, got %d.',
                $expected,
                $entityClass,
                json_encode($criteria, \JSON_THROW_ON_ERROR),
                $actual,
            ),
        );
    }

    /**
     * Refresh entity state from the database (guard against stale state).
     */
    protected function refresh(object $entity): void
    {
        $this->em()->refresh($entity);
    }

    /**
     * Expose the concrete EntityManager with native type for convenience.
     */
    protected function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = parent::em();

        return $em;
    }
}
