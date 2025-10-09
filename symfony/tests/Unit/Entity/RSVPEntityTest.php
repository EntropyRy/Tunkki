<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\RSVP;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\RSVP
 */
final class RSVPEntityTest extends TestCase
{
    public function testLifecycleCallbackSetsCreatedAt(): void
    {
        $entity = new RSVP();
        $this->assertNull(
            $entity->getCreatedAt(),
            'createdAt should be null before PrePersist',
        );

        $entity->setCreatedAtValue();
        $createdAt = $entity->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $now = new \DateTimeImmutable();
        $this->assertLessThanOrEqual(
            2,
            abs($now->getTimestamp() - $createdAt->getTimestamp()),
            'createdAt should be set to now',
        );
    }

    public function testSetAndGetEvent(): void
    {
        $entity = new RSVP();
        $event = $this->createMock(Event::class);

        $entity->setEvent($event);
        $this->assertSame($event, $entity->getEvent());

        $entity->setEvent(null);
        $this->assertNull($entity->getEvent());
    }

    public function testSetAndGetMember(): void
    {
        $entity = new RSVP();
        $member = $this->createMock(Member::class);

        $entity->setMember($member);
        $this->assertSame($member, $entity->getMember());

        $entity->setMember(null);
        $this->assertNull($entity->getMember());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $entity = new RSVP();
        $this->assertNull($entity->getCreatedAt());

        $dt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $entity->setCreatedAt($dt);
        $this->assertSame($dt, $entity->getCreatedAt());
    }

    public function testSetAndGetEmail(): void
    {
        $entity = new RSVP();

        $entity->setEmail('test@example.com');
        $this->assertSame('test@example.com', $entity->getEmail());

        $entity->setEmail(null);
        $this->assertNull($entity->getEmail());
    }

    public function testSetAndGetFirstName(): void
    {
        $entity = new RSVP();

        $entity->setFirstName('John');
        $this->assertSame('John', $entity->getFirstName());

        $entity->setFirstName(null);
        $this->assertNull($entity->getFirstName());
    }

    public function testSetAndGetLastName(): void
    {
        $entity = new RSVP();

        $entity->setLastName('Doe');
        $this->assertSame('Doe', $entity->getLastName());

        $entity->setLastName(null);
        $this->assertNull($entity->getLastName());
    }

    public function testGetNameConcatenatesFirstAndLast(): void
    {
        $entity = new RSVP();
        $entity->setFirstName('Jane');
        $entity->setLastName('Smith');

        $this->assertSame('Jane Smith', $entity->getName());
    }

    public function testGetAvailableLastNameWithMember(): void
    {
        $entity = new RSVP();
        $member = $this->createMock(Member::class);
        $member->method('getLastname')->willReturn('MemberLast');

        $entity->setMember($member);
        $entity->setLastName('RSVPLast');

        $this->assertSame('MemberLast', $entity->getAvailableLastName());
    }

    public function testGetAvailableLastNameWithoutMember(): void
    {
        $entity = new RSVP();
        $entity->setLastName('RSVPLast');

        $this->assertSame('RSVPLast', $entity->getAvailableLastName());
    }

    public function testGetAvailableEmailWithMember(): void
    {
        $entity = new RSVP();
        $member = $this->createMock(Member::class);
        $member->method('getEmail')->willReturn('member@example.com');

        $entity->setMember($member);
        $entity->setEmail('rsvp@example.com');

        $this->assertSame('member@example.com', $entity->getAvailableEmail());
    }

    public function testGetAvailableEmailWithoutMember(): void
    {
        $entity = new RSVP();
        $entity->setEmail('rsvp@example.com');

        $this->assertSame('rsvp@example.com', $entity->getAvailableEmail());
    }

    public function testToStringReturnsId(): void
    {
        $entity = new RSVP();
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, 42);

        $this->assertSame('ID: 42', (string) $entity);
    }

    public function testEdgeCaseSetters(): void
    {
        $entity = new RSVP();

        $entity->setEvent(null);
        $entity->setMember(null);
        // Do not set createdAt to null, as setter requires DateTimeImmutable
        $entity->setEmail(null);
        $entity->setFirstName(null);
        $entity->setLastName(null);

        $this->assertNull($entity->getEvent());
        $this->assertNull($entity->getMember());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getEmail());
        $this->assertNull($entity->getFirstName());
        $this->assertNull($entity->getLastName());
    }
}
