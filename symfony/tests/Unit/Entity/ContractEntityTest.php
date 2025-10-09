<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contract;
use PHPUnit\Framework\TestCase;

/**
 * @group entity
 *
 * @covers \App\Entity\Contract
 *
 * ContractEntityTest
 *
 * Focus:
 *  - Non-null invariants for purpose, ContentFi, createdAt, updatedAt
 *  - Lifecycle callback behavior (createdAt immutable, updatedAt mutating on update)
 *  - Content setters & optional fields (ContentEn, validFrom)
 *  - __toString() fallback logic
 */
final class ContractEntityTest extends TestCase
{
    public function testConstructorInitializesNonNullInvariants(): void
    {
        $c = new Contract();

        self::assertSame('', $c->getPurpose(), 'purpose should default to empty string sentinel');
        self::assertSame('', $c->getContentFi(), 'ContentFi should default to empty string sentinel');
        self::assertInstanceOf(\DateTimeImmutable::class, $c->getCreatedAt(), 'createdAt must be initialized');
        self::assertInstanceOf(\DateTimeImmutable::class, $c->getUpdatedAt(), 'updatedAt must be initialized');
        self::assertGreaterThanOrEqual(
            $c->getCreatedAt()->format('U'),
            $c->getUpdatedAt()->format('U'),
            'updatedAt should be >= createdAt at construction time',
        );
    }

    public function testSettersMutateCoreFields(): void
    {
        $c = new Contract();
        $c->setPurpose('membership-agreement');
        $c->setContentFi('FI body');
        $c->setContentEn('EN body');

        self::assertSame('membership-agreement', $c->getPurpose());
        self::assertSame('FI body', $c->getContentFi());
        self::assertSame('EN body', $c->getContentEn());
    }

    public function testValidFromOptionalFieldCanBeSetAndCleared(): void
    {
        $c = new Contract();
        self::assertNull($this->getPrivate($c, 'validFrom'));

        $ts = new \DateTimeImmutable('+2 days');
        $c->setValidFrom($ts);
        self::assertSame($ts, $c->getValidFrom());

        $c->setValidFrom(null);
        self::assertNull($c->getValidFrom(), 'validFrom can be unset (nullable semantics)');
    }

    public function testLifecycleCallbacksNormalizeTimestamps(): void
    {
        $c = new Contract();

        // Simulate prePersist
        $this->invokeMethod($c, 'setCreatedAtValue');
        $firstCreated = $c->getCreatedAt();
        $firstUpdated = $c->getUpdatedAt();

        self::assertSame(
            $firstCreated->format('U'),
            $firstUpdated->format('U'),
            'At persist time createdAt and updatedAt should be identical',
        );

        // Simulate a later update
        sleep(1); // ensure clock tick
        $this->invokeMethod($c, 'setUpdatedAtValue');
        $secondCreated = $c->getCreatedAt();
        $secondUpdated = $c->getUpdatedAt();

        self::assertSame($firstCreated, $secondCreated, 'createdAt must remain immutable post-persist');
        self::assertNotSame($firstUpdated, $secondUpdated, 'updatedAt must change on update');
        self::assertGreaterThan(
            $firstUpdated->format('U'),
            $secondUpdated->format('U'),
            'updatedAt should advance after update callback',
        );
    }

    public function testToStringFallbackAndCustomPurpose(): void
    {
        $c = new Contract();
        // Empty purpose sentinel -> fallback
        self::assertSame('purpose', (string) $c);

        $c->setPurpose('member-code-of-conduct');
        self::assertSame('member-code-of-conduct', (string) $c);
    }

    /**
     * Invoke a private/protected no-arg method via reflection.
     */
    private function invokeMethod(object $object, string $method): void
    {
        $ref = new \ReflectionClass($object);
        if (!$ref->hasMethod($method)) {
            self::fail("Method {$method} not found on ".$object::class);
        }
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        $m->invoke($object);
    }

    /**
     * Access a private property value (read-only helper).
     */
    private function getPrivate(object $object, string $property)
    {
        $ref = new \ReflectionClass($object);
        if (!$ref->hasProperty($property)) {
            self::fail("Property {$property} not found on ".$object::class);
        }
        $p = $ref->getProperty($property);
        $p->setAccessible(true);

        return $p->getValue($object);
    }
}
