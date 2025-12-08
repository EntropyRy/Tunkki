<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\NakkiBooking;
use App\Entity\Ticket;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Ticket
 */
final class TicketEntityTest extends TestCase
{
    public function testConstructorSetsUpdatedAt(): void
    {
        $ticket = new Ticket();
        $updatedAt = $ticket->getUpdatedAt();
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $now = new \DateTimeImmutable();
        $this->assertLessThanOrEqual(
            2,
            abs($now->getTimestamp() - $updatedAt->getTimestamp()),
            'updatedAt should be set to now',
        );
    }

    public function testSetAndGetEvent(): void
    {
        $ticket = new Ticket();
        $event = $this->createStub(Event::class);

        $ticket->setEvent($event);
        $this->assertSame($event, $ticket->getEvent());
    }

    public function testSetAndGetOwner(): void
    {
        $ticket = new Ticket();
        $owner = $this->createStub(Member::class);

        $ticket->setOwner($owner);
        $this->assertSame($owner, $ticket->getOwner());

        $ticket->setOwner(null);
        $this->assertNull($ticket->getOwner());
    }

    public function testSetAndGetPrice(): void
    {
        $ticket = new Ticket();
        $ticket->setPrice(123);
        $this->assertSame(123, $ticket->getPrice());
    }

    public function testSetAndGetReferenceNumber(): void
    {
        $ticket = new Ticket();
        $ticket->setReferenceNumber(456789);
        $this->assertSame(456789, $ticket->getReferenceNumber());
    }

    public function testSetAndGetStatus(): void
    {
        $ticket = new Ticket();
        $ticket->setStatus('reserved');
        $this->assertSame('reserved', $ticket->getStatus());
    }

    public function testSetAndGetUpdatedAt(): void
    {
        $ticket = new Ticket();
        $dt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $ticket->setUpdatedAt($dt);
        $this->assertSame($dt, $ticket->getUpdatedAt());
    }

    public function testSetAndGetGiven(): void
    {
        $ticket = new Ticket();
        $ticket->setGiven(true);
        $this->assertTrue($ticket->isGiven());

        $ticket->setGiven(false);
        $this->assertFalse($ticket->isGiven());

        $ticket->setGiven(null);
        $this->assertNull($ticket->isGiven());
    }

    public function testSetAndGetEmail(): void
    {
        $ticket = new Ticket();
        $ticket->setEmail('test@example.com');
        $this->assertSame('test@example.com', $ticket->getEmail());

        $ticket->setEmail(null);
        $this->assertNull($ticket->getEmail());
    }

    public function testSetAndGetStripeProductId(): void
    {
        $ticket = new Ticket();
        $ticket->setStripeProductId('prod_123');
        $this->assertSame('prod_123', $ticket->getStripeProductId());

        $ticket->setStripeProductId(null);
        $this->assertNull($ticket->getStripeProductId());
    }

    public function testSetAndGetName(): void
    {
        $ticket = new Ticket();
        $ticket->setName('VIP Ticket');
        $this->assertSame('VIP Ticket', $ticket->getName());

        $ticket->setName(null);
        $this->assertNull($ticket->getName());
    }

    public function testGetOwnerEmailWithOwner(): void
    {
        $ticket = new Ticket();
        $owner = $this->createStub(Member::class);
        $owner->method('getEmail')->willReturn('owner@example.com');

        $ticket->setOwner($owner);
        $this->assertSame('owner@example.com', $ticket->getOwnerEmail());
    }

    public function testGetOwnerEmailWithoutOwner(): void
    {
        $ticket = new Ticket();
        $ticket->setOwner(null);
        $this->assertNull($ticket->getOwnerEmail());
    }

    public function testTicketHolderHasNakkiReturnsNullWithoutEventOrOwner(): void
    {
        $ticket = new Ticket();
        $this->assertNull($ticket->ticketHolderHasNakki());
    }

    public function testTicketHolderHasNakkiDelegatesToEvent(): void
    {
        $ticket = new Ticket();
        $event = $this->createMock(Event::class);
        $owner = $this->createStub(Member::class);
        $nakkiBooking = $this->createStub(NakkiBooking::class);

        $event
            ->expects($this->once())
            ->method('ticketHolderHasNakki')
            ->with($owner)
            ->willReturn($nakkiBooking);

        $ticket->setEvent($event);
        $ticket->setOwner($owner);

        $this->assertSame($nakkiBooking, $ticket->ticketHolderHasNakki());
    }

    public function testToStringReturnsReferenceNumber(): void
    {
        $ticket = new Ticket();
        $ticket->setReferenceNumber(987654);
        $this->assertSame('987654', (string) $ticket);
    }

    public function testEdgeCaseSetters(): void
    {
        $ticket = new Ticket();
        // Event, price, and referenceNumber are non-nullable
        $ticket->setOwner(null);
        $ticket->setStatus('available');
        $ticket->setUpdatedAt(new \DateTimeImmutable());
        $ticket->setGiven(null);
        $ticket->setEmail(null);
        $ticket->setStripeProductId(null);
        $ticket->setName(null);

        $this->assertNull($ticket->getOwner());
        $this->assertSame('available', $ticket->getStatus());
        $this->assertNull($ticket->isGiven());
        $this->assertNull($ticket->getEmail());
        $this->assertNull($ticket->getStripeProductId());
        $this->assertNull($ticket->getName());
    }
}
