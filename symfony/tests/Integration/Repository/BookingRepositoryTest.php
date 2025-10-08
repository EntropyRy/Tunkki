<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Booking;
use App\Repository\BookingRepository;

/**
 * @covers \App\Repository\BookingRepository
 *
 * Integration tests for BookingRepository custom query logic.
 *
 * Historical note:
 *  Earlier versions of findBookingsAtTheSameTime() suffered from operator
 *  precedence issues (OR branch bypassing filters). The repository method
 *  has since been refactored to explicitly group the OR (retrieval OR returning)
 *  inside a single AND expression with the common filters:
 *    (retrieval BETWEEN ... OR returning BETWEEN ...) AND
 *    itemsReturned = false AND cancelled = false AND id != :id
 *
 * This suite:
 *  - Verifies retrieval-side filtering
 *  - Verifies returning-only overlaps are subject to the same filters
 *  - Guards against regressions re‑introducing precedence bugs
 */
final class BookingRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function subjectRepo(): BookingRepository
    {
        /** @var BookingRepository $r */
        $r = $this->em()->getRepository(Booking::class);

        return $r;
    }

    /**
     * Helper to create a Booking with minimal required fields.
     */
    private function makeBooking(
        string $name,
        \DateTimeInterface $retrieval,
        \DateTimeInterface $returning,
        bool $itemsReturned = false,
        bool $cancelled = false,
    ): Booking {
        $b = new Booking();

        // Use setters if they exist; otherwise fall back to reflection.
        $this->assign($b, 'name', $name);
        $this->assign($b, 'retrieval', $retrieval);
        $this->assign($b, 'returning', $returning);
        $this->assign($b, 'itemsReturned', $itemsReturned);
        $this->assign($b, 'cancelled', $cancelled);

        // Populate required non-nullable fields for Booking entity
        // Prefer real setters so Doctrine tracks changes reliably; fallback to reflection if unavailable.
        if (method_exists($b, 'setBookingDate')) {
            $b->setBookingDate(new \DateTimeImmutable());
        } else {
            $this->assign($b, 'bookingDate', new \DateTimeImmutable());
        }
        if (method_exists($b, 'setNumberOfRentDays')) {
            $b->setNumberOfRentDays(1);
        } else {
            $this->assign($b, 'numberOfRentDays', 1);
        }

        return $b;
    }

    /**
     * Assign a value using a conventional setter or reflection if missing.
     */
    private function assign(
        object $entity,
        string $property,
        mixed $value,
    ): void {
        $setter = 'set'.ucfirst($property);
        if (method_exists($entity, $setter)) {
            $entity->{$setter}($value);

            return;
        }

        $ref = new \ReflectionObject($entity);
        // Walk up just in case property is in parent
        while ($ref && !$ref->hasProperty($property)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if ($ref) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($entity, $value);
        } else {
            self::fail(
                "Property '{$property}' not found on ".
                    get_debug_type($entity),
            );
        }
    }

    public function testCountHandledOnlyCountsNonCancelled(): void
    {
        $repo = $this->subjectRepo();

        // Baseline
        $initial = $repo->countHandled();

        $now = new \DateTimeImmutable();
        $b1 = $this->makeBooking(
            'Handled A',
            $now->modify('+1 hour'),
            $now->modify('+2 hours'),
            itemsReturned: false,
            cancelled: false,
        );
        $b2 = $this->makeBooking(
            'Handled B',
            $now->modify('+3 hour'),
            $now->modify('+4 hours'),
            itemsReturned: true, // itemsReturned does not affect countHandled
            cancelled: false,
        );
        $cancelled = $this->makeBooking(
            'Cancelled',
            $now->modify('+5 hour'),
            $now->modify('+6 hours'),
            itemsReturned: false,
            cancelled: true,
        );

        $this->persistAndFlush([$b1, $b2, $cancelled]);

        $after = $repo->countHandled();

        self::assertSame(
            $initial + 2,
            $after,
            'countHandled should increase only for non-cancelled bookings.',
        );
    }

    public function testFindBookingsAtTheSameTimeIntendedRetrievalBranchBehavior(): void
    {
        $repo = $this->subjectRepo();
        $now = new \DateTimeImmutable();

        // Base booking we compare against (unique name to avoid collisions in random order runs)
        $baseName = 'Base-'.uniqid('', true);
        $base = $this->makeBooking(
            $baseName,
            $now->modify('+1 hour'),
            $now->modify('+5 hours'),
        );
        $this->persistAndFlush($base);
        $baseId = $base->getId();
        self::assertNotNull($baseId, 'Base booking must have an ID.');

        // Broaden the time window to reduce flakiness and ensure both retrieval and returning overlaps are captured.
        // Using a wider future horizon also avoids edge timing issues if DateTime resolution differs.
        $rangeStart = $now->modify('-1 hour');
        $rangeEnd = (clone $now)->modify('+8 hours');

        // Overlapping via retrieval (should match)
        $overlapRetrieval = $this->makeBooking(
            'Overlap Retrieval',
            $now->modify('+2 hours'),
            $now->modify('+7 hours'),
        );

        // Overlapping via returning (valid returning-only overlap)
        $overlapReturning = $this->makeBooking(
            'Overlap Returning',
            $now->modify('-5 hours'),
            $now->modify('+3 hours'), // returning inside window
        );

        // Outside window entirely
        $outside = $this->makeBooking(
            'Outside',
            $now->modify('+10 hours'),
            $now->modify('+12 hours'),
        );

        // Overlap retrieval but itemsReturned=true (excluded)
        $itemsReturnedTrue = $this->makeBooking(
            'Items Returned True',
            $now->modify('+3 hours'),
            $now->modify('+4 hours'),
            itemsReturned: true,
        );

        // Overlap retrieval but cancelled=true (excluded)
        $cancelled = $this->makeBooking(
            'Cancelled Overlap',
            $now->modify('+2 hours'),
            $now->modify('+3 hours'),
            cancelled: true,
        );

        $this->persistAndFlush([
            $overlapRetrieval,
            $overlapReturning,
            $outside,
            $itemsReturnedTrue,
            $cancelled,
        ]);

        $results = $repo->findBookingsAtTheSameTime(
            (int) $baseId,
            $rangeStart,
            $rangeEnd,
        );

        self::assertIsArray($results);

        $names = array_map(
            static fn (Booking $b): string => (string) (method_exists(
                $b,
                'getName',
            )
                ? $b->getName()
                : 'unknown'),
            $results,
        );

        // Positive inclusions
        self::assertContains(
            'Overlap Retrieval',
            $names,
            'Booking overlapping by retrieval should be included.',
        );
        self::assertContains(
            'Overlap Returning',
            $names,
            'Returning-only overlap should be included (filters satisfied).',
        );

        // Retrieval-side filter exclusions
        self::assertNotContains(
            'Items Returned True',
            $names,
            'itemsReturned=true should exclude retrieval-overlapping booking.',
        );
        self::assertNotContains(
            'Cancelled Overlap',
            $names,
            'cancelled=true should exclude retrieval-overlapping booking.',
        );

        // Outside
        self::assertNotContains(
            $outside->getId(),
            array_map(fn (Booking $b) => $b->getId(), $results),
            'Non-overlapping booking should be excluded.',
        );

        // Base excluded (by id, not by name)
        $resultIds = array_map(fn (Booking $b) => $b->getId(), $results);
        self::assertNotContains(
            $baseId,
            $resultIds,
            'Base booking (self) id must be excluded by id condition.',
        );
    }

    public function testFindBookingsAtTheSameTimeReturningBranchFiltersApplied(): void
    {
        $repo = $this->subjectRepo();
        $now = new \DateTimeImmutable();

        $baseName = 'BaseReturning-'.uniqid('', true);
        $base = $this->makeBooking(
            $baseName,
            $now->modify('+2 hours'),
            $now->modify('+6 hours'),
        );
        $this->persistAndFlush($base);
        $baseId = $base->getId();
        self::assertNotNull($baseId);

        $rangeStart = $now->modify('-1 hour');
        $rangeEnd = (clone $now)->modify('+10 hours');

        // Valid returning-only (retrieval far before window, returning inside)
        $validReturningOnly = $this->makeBooking(
            'Valid Returning Only',
            $now->modify('-10 hours'),
            $now->modify('+3 hours'),
            itemsReturned: false,
            cancelled: false,
        );

        // Returning-only but itemsReturned=true (should be filtered out)
        $returnedReturningOnly = $this->makeBooking(
            'Returned Returning Only',
            $now->modify('-9 hours'),
            $now->modify('+4 hours'),
            itemsReturned: true,
        );

        // Returning-only but cancelled=true (should be filtered out)
        $cancelledReturningOnly = $this->makeBooking(
            'Cancelled Returning Only',
            $now->modify('-8 hours'),
            $now->modify('+5 hours'),
            cancelled: true,
        );

        $this->persistAndFlush([
            $validReturningOnly,
            $returnedReturningOnly,
            $cancelledReturningOnly,
        ]);

        $results = $repo->findBookingsAtTheSameTime(
            (int) $baseId,
            $rangeStart,
            $rangeEnd,
        );

        self::assertIsArray($results);

        $names = array_map(
            static fn (Booking $b): string => (string) (method_exists(
                $b,
                'getName',
            )
                ? $b->getName()
                : 'unknown'),
            $results,
        );

        self::assertContains(
            'Valid Returning Only',
            $names,
            'Returning-only overlap should be included when filters pass.',
        );
        self::assertNotContains(
            'Returned Returning Only',
            $names,
            'itemsReturned=true returning-only overlap must be excluded.',
        );
        self::assertNotContains(
            'Cancelled Returning Only',
            $names,
            'cancelled=true returning-only overlap must be excluded.',
        );
        $resultIds = array_map(fn (Booking $b) => $b->getId(), $results);
        self::assertNotContains(
            $baseId,
            $resultIds,
            'Base booking must be excluded (by id).',
        );
    }
}
