<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Nakki\NakkiSchedulerResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralised scheduler for Nakki slot generation and reconciliation.
 *
 * Replaces the legacy Sonata admin hooks with a reusable service that can be
 * invoked from Twig Live Components or other orchestration layers.
 */
final readonly class NakkiScheduler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Rebuild bookings for the provided nakki.
     *
     * Existing bookings that match the requested start time are kept (end time
     * is refreshed). Unassigned bookings that no longer fit the schedule are
     * removed. Bookings with assigned members that no longer match are reported
     * as conflicts and left untouched so the caller can decide the next step.
     */
    public function synchronise(Nakki $nakki): NakkiSchedulerResult
    {
        $desiredSlots = $this->buildDesiredSlots($nakki);
        $created = [];
        $removed = [];
        $preserved = [];
        $conflicts = [];

        foreach ($nakki->getNakkiBookings() as $booking) {
            $startKey = $this->buildSlotKey($booking->getStartAt());
            if (isset($desiredSlots[$startKey])) {
                $desired = $desiredSlots[$startKey];
                $booking->setEndAt($desired['end']);
                $preserved[] = $booking;
                unset($desiredSlots[$startKey]);

                continue;
            }

            if (null !== $booking->getMember()) {
                $conflicts[] = $booking;

                continue;
            }

            $nakki->removeNakkiBooking($booking);
            $this->entityManager->remove($booking);
            $removed[] = $booking;
        }

        foreach ($desiredSlots as $slot) {
            $created[] = $this->createBooking($nakki, $slot['start'], $slot['end']);
        }

        // Persist new/removed bookings in batch; callers decide when to flush.
        return new NakkiSchedulerResult(
            created: $created,
            removed: $removed,
            preserved: $preserved,
            conflicts: $conflicts,
            warning: $this->buildWarningMessage($nakki, $conflicts),
        );
    }

    /**
     * Remove and re-create all bookings regardless of conflicts.
     *
     * This method is destructive: existing bookings are deleted even when they
     * have assigned members. Callers must ensure this is acceptable before use.
     */
    public function forceRegenerate(Nakki $nakki): NakkiSchedulerResult
    {
        $removed = [];
        foreach ($nakki->getNakkiBookings() as $booking) {
            $nakki->removeNakkiBooking($booking);
            $this->entityManager->remove($booking);
            $removed[] = $booking;
        }

        $created = [];
        foreach ($this->buildDesiredSlots($nakki) as $slot) {
            $created[] = $this->createBooking($nakki, $slot['start'], $slot['end']);
        }

        return new NakkiSchedulerResult(created: $created, removed: $removed);
    }

    /**
     * Create bookings for a new nakki.
     */
    public function initialise(Nakki $nakki): NakkiSchedulerResult
    {
        $created = [];
        foreach ($this->buildDesiredSlots($nakki) as $slot) {
            $created[] = $this->createBooking($nakki, $slot['start'], $slot['end']);
        }

        return new NakkiSchedulerResult(created: $created);
    }

    /**
     * @return array<string, array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function buildDesiredSlots(Nakki $nakki): array
    {
        $slots = [];
        $interval = $nakki->getNakkiInterval();
        $start = $nakki->getStartAt();
        $end = $nakki->getEndAt();

        if ($start >= $end) {
            return $slots;
        }

        $totalSeconds = $this->intervalToSeconds($interval);
        if (0 >= $totalSeconds) {
            return $slots;
        }

        $cursor = $start;
        $iterationGuard = 0;
        $maxIterations = 1000; // safety guard to prevent infinite loops

        while ($cursor < $end && $iterationGuard < $maxIterations) {
            ++$iterationGuard;
            $next = $cursor->add($interval);
            if ($next > $end) {
                break;
            }

            $slots[$this->buildSlotKey($cursor)] = [
                'start' => $cursor,
                'end' => $next,
            ];

            $cursor = $next;
        }

        return $slots;
    }

    private function createBooking(Nakki $nakki, \DateTimeImmutable $start, \DateTimeImmutable $end): NakkiBooking
    {
        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setEvent($nakki->getEvent());
        $booking->setStartAt($start);
        $booking->setEndAt($end);

        $this->entityManager->persist($booking);
        $nakki->addNakkiBooking($booking);

        return $booking;
    }

    private function buildSlotKey(\DateTimeImmutable $start): string
    {
        return $start->format('c');
    }

    private function intervalToSeconds(\DateInterval $interval): int
    {
        if (1 === $interval->invert) {
            return 0;
        }
        $reference = new \DateTimeImmutable('@0');
        $end = $reference->add($interval);

        return (int) $end->format('U') - (int) $reference->format('U');
    }

    /**
     * Build a human-readable warning for conflict situations.
     */
    private function buildWarningMessage(Nakki $nakki, array $conflicts): ?string
    {
        if ([] === $conflicts) {
            return null;
        }

        $count = \count($conflicts);
        $definition = $nakki->getDefinition()->getNameEn();

        return \sprintf(
            '%d booking(s) with assigned members were left untouched for "%s". Adjust them manually.',
            $count,
            $definition,
        );
    }
}
