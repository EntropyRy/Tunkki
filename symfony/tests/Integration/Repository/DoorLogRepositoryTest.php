<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\DoorLog;
use App\Repository\DoorLogRepository;

/**
 * @covers \App\Repository\DoorLogRepository
 *
 * Integration tests ensuring custom finder logic works against the
 * database using the canonical fixture baseline plus locally created
 * DoorLog entities (wrapped in a transaction by RepositoryTestCase).
 *
 * Strategy:
 *  - We do NOT depend on pre-existing DoorLog fixtures (none provided),
 *    instead we create the minimal viable entities needed per test.
 *  - Because we have not inspected the DoorLog entity here, we use
 *    metadata introspection to populate any required (non-nullable)
 *    scalar fields with generic placeholder values. This keeps the test
 *    resilient if additional required scalar columns are introduced
 *    later (it will auto-populate them).
 *  - For associations that might be required (nullable=false) we skip
 *    auto-handling to avoid deep fixture graphs; if encountered the
 *    test is marked incomplete with a diagnostic so the developer can
 *    decide on a stable strategy (e.g. add dedicated fixtures).
 */
final class DoorLogRepositoryTest extends RepositoryTestCase
{
    private function subjectRepo(): DoorLogRepository
    {
        /** @var DoorLogRepository $r */
        $r = $this->em()->getRepository(DoorLog::class);

        return $r;
    }

    /**
     * Create a DoorLog with a specific createdAt timestamp.
     * Any additional required scalar fields (nullable=false) are
     * populated heuristically.
     */
    private function makeDoorLog(\DateTimeImmutable $createdAt): DoorLog
    {
        $log = new DoorLog();

        // Assign createdAt via setter or reflection.
        $this->assign($log, 'createdAt', $createdAt);

        // Always assign a Member (required association)
        $member = new \App\Entity\Member();
        if (method_exists($member, 'setEmail')) {
            $member->setEmail(uniqid('doorlog-', true).'@example.test');
        }
        if (method_exists($member, 'setFirstname')) {
            $member->setFirstname('Door');
        }
        if (method_exists($member, 'setLastname')) {
            $member->setLastname('Log');
        }
        if (method_exists($member, 'setLocale')) {
            $member->setLocale('en');
        }
        if (method_exists($member, 'setCode')) {
            $member->setCode('DL-'.substr(md5(uniqid()), 0, 10));
        }
        if (method_exists($member, 'setEmailVerified')) {
            $member->setEmailVerified(true);
        }
        $this->em()->persist($member);
        $this->assign($log, 'member', $member);

        // Populate any other required scalar fields (best effort).
        $this->populateRequiredScalars($log);

        return $log;
    }

    /**
     * Populate required (non-nullable) scalar fields that are still null.
     * If a required association is detected, mark the test incomplete
     * to avoid false failures (developer can extend logic later).
     */
    private function populateRequiredScalars(DoorLog $log): void
    {
        $meta = $this->em()->getClassMetadata(DoorLog::class);

        // Handle scalar fields
        foreach ($meta->fieldMappings as $field => $mapping) {
            if ('id' === $field) {
                continue;
            }
            // Skip if nullable or already has a value
            $nullable = \is_array($mapping)
                ? $mapping['nullable'] ?? false
                : $mapping->nullable ?? false;
            if ($nullable) {
                continue;
            }

            // If value already set, skip
            $current = $this->readProperty($log, $field);
            if (null !== $current) {
                continue;
            }

            // Provide a generic default by type
            $type = \is_array($mapping)
                ? $mapping['type'] ?? 'string'
                : $mapping->type ?? 'string';
            $value = match ($type) {
                'string', 'text' => $field.'-test',
                'integer', 'smallint', 'bigint' => 0,
                'boolean' => false,
                'datetime', 'datetimetz' => new \DateTime(),
                'datetime_immutable',
                'datetimetz_immutable' => new \DateTimeImmutable(),
                'float', 'decimal' => 0.0,
                'json' => [],
                default => $field.'-test',
            };

            $this->assign($log, $field, $value);
        }

        // Detect required associations (we do not auto-create them here)
        foreach ($meta->associationMappings as $assoc => $am) {
            // Doctrine 2.17+ may expose mapping objects instead of arrays; normalize joinColumns
            $joinColumns = null;
            if (\is_array($am)) {
                $joinColumns = $am['joinColumns'] ?? null;
            } elseif (isset($am->joinColumns)) {
                // object style
                $joinColumns = $am->joinColumns;
            }
            $nullable = true;
            if (is_iterable($joinColumns)) {
                foreach ($joinColumns as $jc) {
                    $jcNullable = true;
                    if (\is_array($jc)) {
                        $jcNullable = $jc['nullable'] ?? true;
                    } elseif (
                        \is_object($jc)
                        && property_exists($jc, 'nullable')
                    ) {
                        $jcNullable = $jc->nullable ?? true;
                    }
                    if (false === $jcNullable) {
                        $nullable = false;
                        break;
                    }
                }
            }
            if (false === $nullable) {
                $current = $this->readProperty($log, $assoc);
                if (null === $current) {
                    // Auto-fixture strategy for required associations
                    if ('member' === $assoc) {
                        $m = new \App\Entity\Member();
                        $uniq = uniqid('doorlog-', true);
                        if (method_exists($m, 'setEmail')) {
                            $m->setEmail($uniq.'@example.test');
                        }
                        if (method_exists($m, 'setFirstname')) {
                            $m->setFirstname('Door');
                        }
                        if (method_exists($m, 'setLastname')) {
                            $m->setLastname('Log');
                        }
                        if (method_exists($m, 'setLocale')) {
                            $m->setLocale('en');
                        }
                        if (method_exists($m, 'setCode')) {
                            $m->setCode('DL-'.substr(md5($uniq), 0, 10));
                        }
                        if (method_exists($m, 'setEmailVerified')) {
                            $m->setEmailVerified(true);
                        }
                        $this->em()->persist($m);
                        $this->assign($log, $assoc, $m);
                        continue;
                    }
                    // Fallback for any future required association we have not implemented yet.
                    $this->markTestIncomplete(
                        \sprintf(
                            'DoorLog has a required association "%s" with no auto-fixture strategy. Extend populateRequiredScalars() to handle it.',
                            $assoc,
                        ),
                    );
                }
            }
        }
    }

    private function assign(object $obj, string $prop, mixed $value): void
    {
        $setter = 'set'.ucfirst($prop);
        if (method_exists($obj, $setter)) {
            $obj->{$setter}($value);

            return;
        }
        $ref = new \ReflectionObject($obj);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if (!$ref) {
            // Silently ignore if truly unknown (entity may evolve)
            return;
        }
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    private function readProperty(object $obj, string $prop): mixed
    {
        $getter = 'get'.ucfirst($prop);
        $isser = 'is'.ucfirst($prop);
        if (method_exists($obj, $getter)) {
            return $obj->{$getter}();
        }
        if (method_exists($obj, $isser)) {
            return $obj->{$isser}();
        }
        $ref = new \ReflectionObject($obj);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if (!$ref) {
            return null;
        }
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);

        return $p->getValue($obj);
    }

    public function testGetLatestReturnsDescendingLimitedSet(): void
    {
        $repo = $this->subjectRepo();

        // Baseline count
        $initial = $repo->getLatest(50);
        $initialCount = \is_array($initial) ? \count($initial) : 0;

        // Create 5 logs with strictly increasing createdAt (oldest first)
        $now = new \DateTimeImmutable();
        $logs = [];
        for ($i = 0; $i < 5; ++$i) {
            $logs[] = $this->makeDoorLog(
                $now->modify(\sprintf('+%d minutes', $i)),
            );
        }
        foreach ($logs as $l) {
            $this->em()->persist($l);
        }
        $this->em()->flush();

        $latest3 = $repo->getLatest(3);
        self::assertIsArray($latest3);
        self::assertCount(3, $latest3, 'Expected limit of 3 records.');

        // Assert ordering: createdAt DESC
        $timestamps = [];
        foreach ($latest3 as $log) {
            $createdAt = $this->readProperty($log, 'createdAt');
            self::assertInstanceOf(\DateTimeInterface::class, $createdAt);
            $timestamps[] = $createdAt->getTimestamp();
        }
        $sorted = $timestamps;
        rsort($sorted, \SORT_NUMERIC);
        self::assertSame(
            $sorted,
            $timestamps,
            'Expected newest-first ordering.',
        );

        // Null argument => default 10 (or fewer if less available)
        $latestDefault = $repo->getLatest(null);
        self::assertIsArray($latestDefault);
        self::assertTrue(
            \count($latestDefault) <= 10,
            'Null count should default to 10 max.',
        );

        // At least the logs we just added should push total >= initialCount
        $after = $repo->getLatest(50);
        self::assertGreaterThanOrEqual(
            min(50, $initialCount + 5),
            \count($after),
            'Expected total latest count to include newly inserted logs within the 50-item cap.',
        );
    }

    public function testGetSinceFiltersByCreatedAt(): void
    {
        $repo = $this->subjectRepo();

        $now = new \DateTimeImmutable();

        // Create 3 "old" logs (before cutoff)
        $old1 = $this->makeDoorLog($now->modify('-3 hours'));
        $old2 = $this->makeDoorLog($now->modify('-2 hours'));
        $old3 = $this->makeDoorLog($now->modify('-91 minutes'));
        // Cutoff (one hour ago)
        $cutoff = $now->modify('-1 hour');

        // Create 3 "new" logs (after cutoff)
        $new1 = $this->makeDoorLog($now->modify('-50 minutes'));
        $new2 = $this->makeDoorLog($now->modify('-10 minutes'));
        $new3 = $this->makeDoorLog($now->modify('-1 minutes'));

        $toPersist = [$old1, $old2, $old3, $new1, $new2, $new3];
        foreach ($toPersist as $l) {
            $this->em()->persist($l);
        }
        $this->em()->flush();

        $results = $repo->getSince($cutoff);
        self::assertIsArray($results);

        // All results should have createdAt >= cutoff
        foreach ($results as $log) {
            $createdAt = $this->readProperty($log, 'createdAt');
            self::assertInstanceOf(\DateTimeInterface::class, $createdAt);
            self::assertGreaterThanOrEqual(
                $cutoff,
                $createdAt,
                'getSince() returned a log older than cutoff.',
            );
        }

        // Should contain at least the 3 "new" logs we created
        self::assertGreaterThanOrEqual(
            3,
            \count($results),
            'Expected at least the 3 newly inserted post-cutoff logs.',
        );
    }
}
