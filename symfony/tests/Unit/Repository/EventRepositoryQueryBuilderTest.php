<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Repository\EventRepository
 *
 * This test validates that EventRepository composes the expected DQL clauses and parameters
 * without executing queries. We stub Doctrine's ManagerRegistry to return a recording
 * EntityManager + QueryBuilder that capture built state for assertions.
 */
final class EventRepositoryQueryBuilderTest extends TestCase
{
    private EntityManagerInterface $em;
    private ?RecordingQueryBuilder $lastQB = null;
    private EventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = $this->createMock(EntityManagerInterface::class);

        // Provide dependencies needed by QueryBuilder and ServiceEntityRepository internals
        $this->em
            ->method('getExpressionBuilder')
            ->willReturn(new \Doctrine\ORM\Query\Expr());
        $this->em
            ->method('getClassMetadata')
            ->willReturn(new ClassMetadata("App\Entity\Event"));

        // Return a RecordingQueryBuilder and remember the last instance
        $this->em
            ->method('createQueryBuilder')
            ->willReturnCallback(function () {
                $this->lastQB = new RecordingQueryBuilder($this->em);

                return $this->lastQB;
            });

        // Make QueryBuilder->getQuery() non-executing by routing via EntityManager::createQuery()
        $this->em
            ->method('createQuery')
            ->willReturnCallback(function (string $dql) {
                return new RecordingQuery($this->em);
            });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $this->repo = new EventRepository($registry);
    }

    public function testGetSitemapEventsBuildsExpectedQuery(): void
    {
        // Act: triggers the repository to build and "execute" the query (non-executing stub).
        $result = $this->repo->getSitemapEvents();

        // Assert: non-executing stub returns an empty result set.
        self::assertIsArray($result);
        self::assertSame([], $result);

        $qb = $this->lastQB;
        self::assertNotNull($qb, 'QueryBuilder should have been created.');

        // Assert select/from baseline from ServiceEntityRepository::createQueryBuilder('e')
        self::assertSame(['e'], $qb->select);
        self::assertSame("App\Entity\Event", $qb->from['class']);
        self::assertSame('e', $qb->from['alias']);

        // Assert where clauses and parameters
        self::assertContains('e.publishDate <= :now', $qb->conditions);
        self::assertContains('e.published = :pub', $qb->conditions);
        self::assertContains('e.externalUrl = :ext', $qb->conditions);

        self::assertArrayHasKey('now', $qb->parameters);
        self::assertArrayHasKey('pub', $qb->parameters);
        self::assertArrayHasKey('ext', $qb->parameters);

        self::assertTrue($qb->parameters['pub']);
        self::assertFalse($qb->parameters['ext']);

        // Assert ordering
        self::assertSame([['e.EventDate', 'DESC']], $qb->orderBy);
    }

    public function testFindPublicEventsByTypeBuildsExpectedQuery(): void
    {
        $this->repo->findPublicEventsByType('Announcement');

        $qb = $this->lastQB;
        self::assertNotNull($qb);

        self::assertContains('LOWER(r.type) = :val', $qb->conditions);
        self::assertContains('r.published = :pub', $qb->conditions);
        self::assertContains('r.publishDate <= :pubDate', $qb->conditions);

        self::assertSame(
            'announcement',
            $qb->parameters['val'],
            'Type parameter should be lowercased.',
        );
        self::assertTrue($qb->parameters['pub']);
        self::assertArrayHasKey('pubDate', $qb->parameters);

        self::assertSame([['r.EventDate', 'DESC']], $qb->orderBy);
    }

    public function testFindAllByNotTypeBuildsExpectedQuery(): void
    {
        $this->repo->findAllByNotType('announcement');

        $qb = $this->lastQB;
        self::assertNotNull($qb);

        self::assertContains('LOWER(r.type) != :val', $qb->conditions);
        self::assertSame('announcement', $qb->parameters['val']);
        // No publish gating in this method:
        self::assertFalse(
            \in_array('r.published = :pub', $qb->conditions, true),
            'findAllByNotType must not add published gating.',
        );

        self::assertSame([['r.EventDate', 'DESC']], $qb->orderBy);
    }
}

/**
 * Chainable QueryBuilder stub that records select/from/where/params/orderBy
 * and returns a RecordingQuery that never hits a database.
 */
final class RecordingQueryBuilder extends \Doctrine\ORM\QueryBuilder
{
    /** @var list<string> */
    public array $select = [];
    /** @var array{class: string, alias: string}|null */
    public ?array $from = null;
    /** @var list<string> */
    public array $conditions = [];
    /** @var array<string, mixed> */
    public array $parameters = [];
    /** @var list<array{0: string, 1: string}> */
    public array $orderBy = [];
    public ?int $maxResults = null;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    public function getDQL(): string
    {
        // Parent QueryBuilder calls $em->createQuery($this->getDQL())
        // We bypass DQL generation since our EntityManager mock returns a RecordingQuery.
        return 'DUMMY';
    }

    public function select(mixed ...$select): static
    {
        foreach ($select as $s) {
            $this->select[] = $s;
        }

        return $this;
    }

    public function from(
        string $from,
        string $alias,
        ?string $indexBy = null,
    ): static {
        $this->from = ['class' => $from, 'alias' => $alias];

        return $this;
    }

    public function andWhere(mixed ...$predicates): static
    {
        foreach ($predicates as $p) {
            $this->conditions[] = $p;
        }

        return $this;
    }

    public function setParameter(
        string|int $key,
        mixed $value,
        \Doctrine\DBAL\ParameterType|\Doctrine\DBAL\ArrayParameterType|string|int|null $type = null,
    ): static {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function orderBy(
        \Doctrine\ORM\Query\Expr\OrderBy|string $sort,
        ?string $order = null,
    ): static {
        $sortVal =
            $sort instanceof \Doctrine\ORM\Query\Expr\OrderBy
                ? (string) $sort
                : $sort;
        $this->orderBy[] = [$sortVal, strtoupper($order ?? 'ASC')];

        return $this;
    }

    public function setMaxResults(?int $maxResults): static
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    public function getQuery(): \Doctrine\ORM\Query
    {
        return new RecordingQuery($this->getEntityManager());
    }
}

/**
 * Non-executing Query stub returning deterministic values.
 */
final class RecordingQuery extends \Doctrine\ORM\Query
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    public function getResult(
        string|int $hydrationMode = self::HYDRATE_OBJECT,
    ): mixed {
        // Non-executing: return an empty result set
        return [];
    }

    public function getOneOrNullResult(
        string|int|null $hydrationMode = null,
    ): mixed {
        // Non-executing: emulate "no row"
        return null;
    }
}
