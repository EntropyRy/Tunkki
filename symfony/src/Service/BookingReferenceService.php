<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;

/**
 * BookingReferenceService.
 *
 * Centralized service for assigning a reference number and renter hash to Booking entities; also exposes a public calculator for Ticket reference numbers.
 *
 * Usage guidelines:
 * - Call assignReferenceAndHash() after the Booking has an identifier (post-persist)
 *   so that ReferenceNumber calculation can include the primary key.
 * - This mirrors the existing logic used in BookingAdmin, but provides a reusable,
 *   testable service for non-admin contexts (e.g., repository tests, command handlers).
 */
final readonly class BookingReferenceService
{
    public function __construct(
        private int $referenceAdd = 1220,
        private int $referenceStart = 303,
    ) {
    }

    /**
     * Ensure both referenceNumber and renterHash are populated on the given Booking.
     * No-ops for fields that are already set.
     */
    public function assignReferenceAndHash(Booking $booking): void
    {
        if (
            '' === $booking->getReferenceNumber()
        ) {
            $booking->setReferenceNumber(
                (string) $this->calculateReferenceNumber(
                    $booking,
                    $this->referenceAdd,
                    $this->referenceStart,
                ),
            );
        }

        if (
            '' === $booking->getRenterHash()
        ) {
            $booking->setRenterHash($this->calculateOwnerHash($booking));
        }
    }

    /**
     * Calculate renter hash using the same strategy as BookingAdmin::calculateOwnerHash().
     * Combines a shuffled reference number and the booking name, then MD5 and lowercase.
     */
    private function calculateOwnerHash(Booking $booking): string
    {
        $ref = $booking->getReferenceNumber();
        $name = $booking->getName();

        $string = str_shuffle($ref).$name;

        return strtolower(md5($string));
    }

    public function calculateReferenceNumber(
        object $object,
        int $add,
        int $start,
    ): int {
        $ki = 0;
        $summa = 0;
        $kertoimet = [7, 3, 1];
        $id = (int) $object->getId() + $add;
        $viite = $start.$id;

        for ($i = \strlen($viite); $i > 0; --$i) {
            $summa += (int) substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
        $cast = $viite.(10 - ($summa % 10)) % 10;

        return (int) $cast;
    }
}
