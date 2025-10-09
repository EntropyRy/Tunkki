<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Service\BookingReferenceService;
use PHPUnit\Framework\TestCase;

final class BookingReferenceServiceTest extends TestCase
{
    public function testCalculateReferenceNumberProducesExpectedForGivenIdAddStart(): void
    {
        $svc = new BookingReferenceService();

        // Anonymous object that mimics an entity with an id
        $obj = new class {
            public function getId(): int
            {
                return 42;
            }
        };

        // Using the same algorithm as production, manual expected:
        // start=303, add=1220, id=42 => viite="3031262" => check digit 9 => 30312629
        $result = $svc->calculateReferenceNumber($obj, 1220, 303);

        $this->assertSame(30312629, $result);
    }

    public function testCalculateReferenceNumberTypicalTicketParameters(): void
    {
        $svc = new BookingReferenceService();

        $obj = new class {
            public function getId(): int
            {
                return 5;
            }
        };

        // Typical ticket params (as used by admin/subscriber): add=9000, start=909
        // id=5 => viite="9099005" => check digit 2 => 90990052
        $result = $svc->calculateReferenceNumber($obj, 9000, 909);

        $this->assertSame(90990052, $result);
    }

    public function testAssignReferenceAndHashSetsBothWhenMissing(): void
    {
        $svc = new BookingReferenceService(
            referenceAdd: 1220,
            referenceStart: 303,
        );

        $booking = new Booking();
        $booking->setName('Unit Test Booking');

        // Force a deterministic primary key on the entity (so referenceNumber becomes deterministic)
        $this->setEntityId($booking, 42);

        // Precondition: both fields start as empty sentinel strings (non-nullable)
        $this->assertSame('', $booking->getReferenceNumber());
        $this->assertSame('', $booking->getRenterHash());

        $svc->assignReferenceAndHash($booking);

        // referenceNumber should be calculated using the known id/add/start tuple
        // Expected: "30312629" (string, since Booking referenceNumber is string-typed)
        $this->assertSame('30312629', $booking->getReferenceNumber());

        // renterHash should be a lowercase md5 hex string (32 chars)
        $hash = $booking->getRenterHash();
        $this->assertIsString($hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);

        // Idempotency: re-invoking should not change already-populated values
        $firstHash = $booking->getRenterHash();
        $svc->assignReferenceAndHash($booking);
        $this->assertSame('30312629', $booking->getReferenceNumber());
        $this->assertSame($firstHash, $booking->getRenterHash());
    }

    public function testAssignReferenceAndHashDoesNotOverrideExistingValues(): void
    {
        $svc = new BookingReferenceService(
            referenceAdd: 1220,
            referenceStart: 303,
        );

        $booking = new Booking();
        $booking->setName('Pre-Seeded');
        $this->setEntityId($booking, 999);

        $existingRef = 'EXISTING-REF';
        $existingHash = 'deadbeefdeadbeefdeadbeefdeadbeef';

        // Seed both fields
        $booking->setReferenceNumber($existingRef);
        $booking->setRenterHash($existingHash);

        $svc->assignReferenceAndHash($booking);

        // Ensure service is a no-op for already-populated fields
        $this->assertSame($existingRef, $booking->getReferenceNumber());
        $this->assertSame($existingHash, $booking->getRenterHash());
    }

    /**
     * Set the private "id" on a Doctrine entity without relying on reflection accessibility.
     * This uses a bound closure to modify the private property in class scope.
     */
    private function setEntityId(object $entity, int $id): void
    {
        $setter = \Closure::bind(
            static function (object $obj, int $value): void {
                // @phpstan-ignore-next-line: accessing private property within bound scope
                $obj->id = $value;
            },
            null,
            $entity::class,
        );
        $setter($entity, $id);
    }
}
