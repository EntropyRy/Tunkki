<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Booking;
use App\Entity\Item;
use App\Entity\StatusEvent;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\StatusEvent
 */
final class StatusEventEntityTest extends TestCase
{
    public function testLifecycleCallbacksSetCreatedAndUpdatedAt(): void
    {
        $event = new StatusEvent();

        // Simulate PrePersist lifecycle
        $event->setCreatedAtValue();
        $createdAt = $event->getCreatedAt();
        $updatedAt = $event->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertEquals($createdAt, $updatedAt);

        // Simulate PreUpdate lifecycle
        // Wait a moment to ensure updatedAt changes
        usleep(1000);
        $event->setUpdatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $event->getUpdatedAt(),
        );
        $this->assertNotEquals($createdAt, $event->getUpdatedAt());
    }

    public function testDescriptionNullability(): void
    {
        $event = new StatusEvent();
        $this->assertNull($event->getDescription());

        $event->setDescription('Test description');
        $this->assertSame('Test description', $event->getDescription());

        $event->setDescription(null);
        $this->assertNull($event->getDescription());
    }

    public function testToStringWithItem(): void
    {
        $event = new StatusEvent();
        $item = $this->createMock(Item::class);
        $item->method('getName')->willReturn('TestItem');
        $event->setItem($item);

        $this->assertStringContainsString(
            'Event for TestItem',
            (string) $event,
        );
    }

    public function testToStringWithBooking(): void
    {
        $event = new StatusEvent();
        $booking = $this->createMock(Booking::class);
        $booking->method('getName')->willReturn('TestBooking');
        $event->setBooking($booking);

        $this->assertStringContainsString(
            'Event for TestBooking',
            (string) $event,
        );
    }

    public function testToStringWithNoAssociations(): void
    {
        $event = new StatusEvent();
        $this->assertSame('No associated item', (string) $event);
    }

    public function testCreatorAndModifierSettersAndGetters(): void
    {
        $event = new StatusEvent();
        $creator = $this->createMock(User::class);
        $modifier = $this->createMock(User::class);

        $event->setCreator($creator);
        $event->setModifier($modifier);

        $this->assertSame($creator, $event->getCreator());
        $this->assertSame($modifier, $event->getModifier());

        $event->setCreator(null);
        $event->setModifier(null);

        $this->assertNull($event->getCreator());
        $this->assertNull($event->getModifier());
    }

    public function testEdgeCaseSetters(): void
    {
        $event = new StatusEvent();

        // Setting createdAt and updatedAt manually
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');
        $event->setCreatedAt($now);
        $event->setUpdatedAt($now);

        $this->assertSame($now, $event->getCreatedAt());
        $this->assertSame($now, $event->getUpdatedAt());

        // Setting Item and Booking to null explicitly
        $event->setItem(null);
        $event->setBooking(null);

        $this->assertNull($event->getItem());
        $this->assertNull($event->getBooking());
    }
}
