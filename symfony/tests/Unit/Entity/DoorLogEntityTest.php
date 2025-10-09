<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DoorLog;
use App\Entity\Member;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * @group entity
 *
 * @covers \App\Entity\DoorLog
 *
 * DoorLogEntityTest
 *
 * Focus:
 *  - Non-null immutable-style creation timestamp initialization.
 *  - prePersist() must NOT overwrite an explicitly set createdAt.
 *  - Member association non-null semantics (DB-level) behave in-memory.
 *  - Optional message field nullable/assignable.
 */
final class DoorLogEntityTest extends TestCase
{
    public function testConstructorInitializesCreatedAt(): void
    {
        $log = new DoorLog();

        $createdAt = $log->getCreatedAt();
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $createdAt,
            'createdAt must be initialized as DateTimeImmutable',
        );

        $now = new \DateTimeImmutable();
        $delta = $now->getTimestamp() - $createdAt->getTimestamp();
        Assert::assertLessThanOrEqual(
            2,
            $delta,
            'createdAt should be set close to construction time (<=2s drift tolerance)',
        );
    }

    public function testSetCreatedAtAllowsExplicitBackfillAndIsNotOverwrittenByPrePersist(): void
    {
        $log = new DoorLog();

        // Backfill with an earlier timestamp (e.g. importing historical logs)
        $historical = new \DateTimeImmutable('-5 days');
        $log->setCreatedAt($historical);
        Assert::assertSame(
            $historical,
            $log->getCreatedAt(),
            'Explicit setCreatedAt should override constructor timestamp',
        );

        // Invoke lifecycle callback (simulating Doctrine prePersist)
        $this->invokeLifecycle($log, 'prePersist');

        Assert::assertSame(
            $historical,
            $log->getCreatedAt(),
            'prePersist must not overwrite an already assigned createdAt',
        );
    }

    public function testMemberAndMessageMutators(): void
    {
        $log = new DoorLog();

        $member = new Member(); // Minimal instantiation; invariants enforced at persistence layer.
        $log->setMember($member);
        Assert::assertSame(
            $member,
            $log->getMember(),
            'Member reference should be retained',
        );

        Assert::assertNull(
            $log->getMessage(),
            'Default message should be null',
        );

        $log->setMessage('Door opened by RFID');
        Assert::assertSame('Door opened by RFID', $log->getMessage());

        $log->setMessage(null);
        Assert::assertNull(
            $log->getMessage(),
            'Message should be nullable and clearable',
        );
    }

    /**
     * Invoke a (protected/private) lifecycle method via reflection if present.
     */
    private function invokeLifecycle(object $object, string $method): void
    {
        $ref = new \ReflectionClass($object);
        if (!$ref->hasMethod($method)) {
            Assert::fail(
                "Lifecycle method {$method} not found on ".$object::class,
            );
        }
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        $m->invoke($object);
    }
}
