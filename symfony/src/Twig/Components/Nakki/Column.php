<?php

declare(strict_types=1);

namespace App\Twig\Components\Nakki;

use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Repository\NakkiBookingRepository;
use App\Repository\NakkiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Column
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp(onUpdated: 'nakkiIdUpdated')]
    public int $nakkiId;

    #[LiveProp(writable: true)]
    public string $newSlotStart = '';

    #[LiveProp(writable: true)]
    public int $newSlotIntervalHours = 1;

    #[LiveProp(writable: true)]
    public int $newSlotCount = 1;

    #[LiveProp(writable: true)]
    public bool $disableBookings = false;

    #[LiveProp(writable: true)]
    public string $viewMode = 'edit'; // 'edit' or 'schedule'

    public int $displayIntervalHours = 1;

    public ?string $notice = null;
    public ?string $error = null;

    private ?Nakki $nakki = null;

    public function __construct(
        private readonly NakkiRepository $nakkiRepository,
        private readonly NakkiBookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function mount(int $nakkiId): void
    {
        $this->nakkiId = $nakkiId;
        $this->loadFromEntity();
    }

    public function nakkiIdUpdated(): void
    {
        $this->resetCache();
        $this->loadFromEntity();
    }

    public function getNakkiView(): Nakki
    {
        return $this->getNakki();
    }

    #[LiveAction]
    public function addSlots(#[LiveArg] string|int|null $intervalHours = null, #[LiveArg] string|int|null $slotCount = null): void
    {
        $this->notice = null;
        $this->error = null;

        // Cast to int as they come as strings from the button attributes
        $count = max(1, (int) ($slotCount ?? $this->newSlotCount));
        $intervalHours = max(1, (int) ($intervalHours ?? $this->newSlotIntervalHours));
        $interval = new \DateInterval(\sprintf('PT%dH', $intervalHours));

        $start = $this->parseDateTime($this->newSlotStart);
        if (!$start instanceof \DateTimeImmutable) {
            $bookings = $this->getBookings();
            if ([] !== $bookings) {
                $last = end($bookings);
                $start = $last->getEndAt();
            } else {
                $start = $this->getNakki()->getStartAt();
            }
        }

        if (!$start instanceof \DateTimeImmutable) {
            $start = \DateTimeImmutable::createFromInterface($start);
        }

        $nakki = $this->getNakki();
        $created = [];
        $cursor = $start;

        for ($i = 0; $i < $count; ++$i) {
            $end = $cursor->add($interval);

            $booking = new NakkiBooking();
            $booking->setNakki($nakki);
            $booking->setEvent($nakki->getEvent());
            $booking->setStartAt($cursor);
            $booking->setEndAt($end);
            $this->entityManager->persist($booking);
            $nakki->addNakkiBooking($booking);

            $created[] = $booking;
            $cursor = $end;
        }

        $this->recalculateBounds($nakki);
        $this->entityManager->flush();

        $createdCount = \count($created);
        $this->notice = $this->translator->trans('nakkikone.column.added_slots', [
            '%count%' => $createdCount,
            'count' => $createdCount,
        ]);

        $this->resetCache();
        $this->loadFromEntity();
    }

    #[LiveAction]
    public function removeSlot(#[LiveArg] int $bookingId): void
    {
        $this->notice = null;
        $this->error = null;

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking instanceof NakkiBooking || $booking->getNakki() !== $this->getNakki()) {
            $this->error = $this->translator->trans('nakkikone.column.booking_not_found');

            return;
        }

        if ($booking->getMember() instanceof Member) {
            $this->error = $this->translator->trans('nakkikone.column.remove_reserved_denied');

            return;
        }

        $nakki = $this->getNakki();
        $nakki->removeNakkiBooking($booking);
        $this->entityManager->remove($booking);
        $this->recalculateBounds($nakki);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans('nakkikone.column.booking_removed');

        $this->resetCache();
        $this->loadFromEntity();
    }

    #[LiveAction]
    public function addSlotBefore(): void
    {
        $this->notice = null;
        $this->error = null;

        $bookings = $this->getBookings();
        if ([] === $bookings) {
            $this->error = $this->translator->trans('nakkikone.column.no_bookings_to_add_before');

            return;
        }

        $nakki = $this->getNakki();
        $firstBooking = $bookings[0];
        // Use the nakki's default interval
        $interval = $nakki->getNakkiInterval();

        $end = $firstBooking->getStartAt();
        $start = $end->sub($interval);

        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setEvent($nakki->getEvent());
        $booking->setStartAt($start);
        $booking->setEndAt($end);

        $this->entityManager->persist($booking);
        $nakki->addNakkiBooking($booking);
        $this->recalculateBounds($nakki);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans('nakkikone.column.slot_added_before');

        $this->resetCache();
        $this->loadFromEntity();
    }

    #[LiveAction]
    public function addSlotAfter(): void
    {
        $this->notice = null;
        $this->error = null;

        $bookings = $this->getBookings();
        $nakki = $this->getNakki();
        // Use the nakki's default interval
        $interval = $nakki->getNakkiInterval();

        $isFirstSlot = [] === $bookings;

        if ($isFirstSlot) {
            // If no bookings, create one starting at nakki start time
            $start = $nakki->getStartAt();
        } else {
            $lastBooking = end($bookings);
            $start = $lastBooking->getEndAt();
        }

        $end = $start->add($interval);

        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setEvent($nakki->getEvent());
        $booking->setStartAt($start);
        $booking->setEndAt($end);

        $this->entityManager->persist($booking);
        $nakki->addNakkiBooking($booking);
        $this->recalculateBounds($nakki);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans(
            $isFirstSlot ? 'nakkikone.column.first_slot_added' : 'nakkikone.column.slot_added_after'
        );

        $this->resetCache();
        $this->loadFromEntity();
    }

    #[LiveAction]
    public function addSlotAtTime(#[LiveArg] string $startTime, #[LiveArg] int $intervalHours): void
    {
        $this->notice = null;
        $this->error = null;

        $start = new \DateTimeImmutable($startTime);
        $nakki = $this->getNakki();

        // Use the provided interval hours (from existing slot at this time)
        $interval = new \DateInterval(\sprintf('PT%dH', max(1, $intervalHours)));
        $end = $start->add($interval);

        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setEvent($nakki->getEvent());
        $booking->setStartAt($start);
        $booking->setEndAt($end);

        $this->entityManager->persist($booking);
        $nakki->addNakkiBooking($booking);
        $this->recalculateBounds($nakki);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans('nakkikone.column.slot_added_at_time');

        $this->resetCache();
        $this->loadFromEntity();
    }

    #[LiveAction]
    public function saveDisable(): void
    {
        $nakki = $this->getNakki();
        $nakki->setDisableBookings($this->disableBookings);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans($this->disableBookings ? 'nakkikone.column.disabled' : 'nakkikone.column.enabled');
    }

    #[LiveAction]
    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'edit' ? 'schedule' : 'edit';
    }

    #[LiveAction]
    public function updateInterval(#[LiveArg] string|int $intervalHours): void
    {
        $this->notice = null;
        $this->error = null;

        $hours = max(1, (int) $intervalHours);
        $nakki = $this->getNakki();
        $nakki->setNakkiInterval(new \DateInterval(\sprintf('PT%dH', $hours)));
        $this->entityManager->flush();

        $this->notice = $this->translator->trans('Interval updated to {hours}h', ['hours' => $hours]);

        $this->resetCache();
        $this->loadFromEntity();
    }

    #[LiveAction]
    public function editColumn(): void
    {
        $nakki = $this->getNakki();
        $definition = $nakki->getDefinition();
        $definitionId = $definition->getId();

        if (null !== $definitionId) {
            $this->emitUp('nakki:edit', [
                'definitionId' => $definitionId,
            ]);
        }
    }

    #[LiveAction]
    public function deleteColumn(): void
    {
        $nakki = $this->getNakki();
        foreach ($nakki->getNakkiBookings() as $booking) {
            if (null !== $booking->getMember()) {
                $this->error = $this->translator->trans('nakkikone.column.delete_reserved_denied');

                return;
            }
        }

        foreach ($nakki->getNakkiBookings() as $booking) {
            $nakki->removeNakkiBooking($booking);
            $this->entityManager->remove($booking);
        }

        $this->entityManager->remove($nakki);
        $this->entityManager->flush();
        $this->emitUp('nakki:removed', [
            'id' => $this->nakkiId,
        ]);
    }

    /**
     * @return list<NakkiBooking>
     */
    public function getBookings(): array
    {
        $bookings = $this->getNakki()->getNakkiBookings()->toArray();
        usort(
            $bookings,
            static fn (NakkiBooking $a, NakkiBooking $b): int => $a->getStartAt() <=> $b->getStartAt(),
        );

        return $bookings;
    }

    /**
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable, bookings: list<NakkiBooking>}>
     */
    public function getBookingGroups(): array
    {
        $groups = [];
        foreach ($this->getBookings() as $booking) {
            // Group by both start AND end time to separate different intervals
            $key = $booking->getStartAt()->format('c').'|'.$booking->getEndAt()->format('c');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'start' => $booking->getStartAt(),
                    'end' => $booking->getEndAt(),
                    'bookings' => [],
                ];
            }

            $groups[$key]['bookings'][] = $booking;
        }

        return array_values($groups);
    }

    /**
     * Get schedule grid for timetable view.
     * Returns hourly time slots with bookings that span multiple rows.
     *
     * @return array{timeSlots: list<array{time: \DateTimeImmutable, bookings: list<array{booking: NakkiBooking, rowspan: int, column: int}>}>, maxColumns: int}
     */
    public function getScheduleGrid(): array
    {
        $bookings = $this->getBookings();
        if ([] === $bookings) {
            return ['timeSlots' => [], 'maxColumns' => 1];
        }

        // Find time range (earliest to latest)
        $earliestStart = $bookings[0]->getStartAt();
        $latestEnd = $bookings[0]->getEndAt();

        foreach ($bookings as $booking) {
            if ($booking->getStartAt() < $earliestStart) {
                $earliestStart = $booking->getStartAt();
            }
            if ($booking->getEndAt() > $latestEnd) {
                $latestEnd = $booking->getEndAt();
            }
        }

        // Round to hour boundaries
        $startHour = new \DateTimeImmutable($earliestStart->format('Y-m-d H:00:00'));
        $endHour = new \DateTimeImmutable($latestEnd->format('Y-m-d H:00:00'));
        if ($latestEnd->format('i:s') !== '00:00') {
            $endHour = $endHour->modify('+1 hour');
        }

        // Create hourly slots
        $timeSlots = [];
        $current = $startHour;
        while ($current < $endHour) {
            $timeSlots[] = [
                'time' => $current,
                'bookings' => [],
            ];
            $current = $current->modify('+1 hour');
        }

        // Assign bookings to slots and calculate layout
        $maxColumns = 1;
        foreach ($bookings as $booking) {
            $bookingStart = $booking->getStartAt();
            $bookingEnd = $booking->getEndAt();

            // Calculate duration in hours (rounded)
            $durationSeconds = $bookingEnd->getTimestamp() - $bookingStart->getTimestamp();
            $durationHours = max(1, (int) round($durationSeconds / 3600));

            // Find which slot this booking starts in
            $slotIndex = null;
            foreach ($timeSlots as $idx => $slot) {
                $slotTime = $slot['time'];
                $nextSlotTime = $slotTime->modify('+1 hour');

                // Booking starts in this hour slot if it starts at or after slot time and before next slot
                if ($bookingStart >= $slotTime && $bookingStart < $nextSlotTime) {
                    $slotIndex = $idx;
                    break;
                }
            }

            if (null === $slotIndex) {
                continue; // Shouldn't happen, but safety check
            }

            // Determine column (find first available column in this time range)
            $column = 0;
            $columnUsed = false;
            do {
                $columnUsed = false;
                // Check if this column is used in any of the hours this booking spans
                for ($i = 0; $i < $durationHours; ++$i) {
                    $checkSlotIdx = $slotIndex + $i;
                    if (!isset($timeSlots[$checkSlotIdx])) {
                        break;
                    }

                    foreach ($timeSlots[$checkSlotIdx]['bookings'] as $existingBooking) {
                        if ($existingBooking['column'] === $column) {
                            $columnUsed = true;
                            break 2;
                        }
                    }
                }

                if ($columnUsed) {
                    ++$column;
                }
            } while ($columnUsed);

            $maxColumns = max($maxColumns, $column + 1);

            // Add booking to the first slot it appears in (with rowspan)
            $timeSlots[$slotIndex]['bookings'][] = [
                'booking' => $booking,
                'rowspan' => $durationHours,
                'column' => $column,
            ];

            // Mark the column as occupied in subsequent hours (for layout calculation)
            for ($i = 1; $i < $durationHours; ++$i) {
                $occupiedSlotIdx = $slotIndex + $i;
                if (isset($timeSlots[$occupiedSlotIdx])) {
                    // Add placeholder to mark column as occupied
                    $timeSlots[$occupiedSlotIdx]['bookings'][] = [
                        'booking' => null,
                        'rowspan' => 0,
                        'column' => $column,
                    ];
                }
            }
        }

        return ['timeSlots' => $timeSlots, 'maxColumns' => $maxColumns];
    }

    /**
     * Get bookings grouped by start time, then by interval.
     *
     * @return list<array{startTime: \DateTimeImmutable, intervalGroups: list<array{start: \DateTimeImmutable, end: \DateTimeImmutable, bookings: list<NakkiBooking>}>}>
     */
    public function getNestedBookingGroups(): array
    {
        // First group by start time only
        $byStartTime = [];
        foreach ($this->getBookings() as $booking) {
            $startKey = $booking->getStartAt()->format('c');
            if (!isset($byStartTime[$startKey])) {
                $byStartTime[$startKey] = [
                    'startTime' => $booking->getStartAt(),
                    'intervalGroups' => [],
                ];
            }

            // Within each start time, group by end time (interval)
            $intervalKey = $booking->getEndAt()->format('c');
            if (!isset($byStartTime[$startKey]['intervalGroups'][$intervalKey])) {
                $byStartTime[$startKey]['intervalGroups'][$intervalKey] = [
                    'start' => $booking->getStartAt(),
                    'end' => $booking->getEndAt(),
                    'bookings' => [],
                ];
            }

            $byStartTime[$startKey]['intervalGroups'][$intervalKey]['bookings'][] = $booking;
        }

        // Convert to indexed arrays
        $result = [];
        foreach ($byStartTime as $group) {
            $group['intervalGroups'] = array_values($group['intervalGroups']);
            $result[] = $group;
        }

        return $result;
    }

    private function loadFromEntity(): void
    {
        $nakki = $this->getNakki();
        $this->disableBookings = (bool) $nakki->isDisableBookings();
        $this->displayIntervalHours = $this->intervalToHours($nakki->getNakkiInterval());
        $bookings = $this->getBookings();
        $nextStart = [] !== $bookings ? end($bookings)->getEndAt() : $nakki->getStartAt();
        $this->newSlotStart = $nextStart instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($nextStart)->format('Y-m-d\TH:i')
            : new \DateTimeImmutable()->format('Y-m-d\TH:i');
        $this->newSlotIntervalHours = max(1, $this->intervalToHours($nakki->getNakkiInterval()));
        $this->newSlotCount = 1;
    }

    private function getNakki(): Nakki
    {
        if (!$this->nakki instanceof Nakki) {
            $nakki = $this->nakkiRepository->find($this->nakkiId);
            if (!$nakki instanceof Nakki) {
                throw new \RuntimeException('Nakki not found.');
            }
            $this->nakki = $nakki;
        }

        return $this->nakki;
    }

    private function resetCache(): void
    {
        $this->nakki = null;
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if ('' === trim($value)) {
            return null;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);

        return false === $dateTime ? null : $dateTime;
    }

    private function recalculateBounds(Nakki $nakki): void
    {
        $bookings = $nakki->getNakkiBookings()->toArray();
        if ([] === $bookings) {
            return;
        }

        usort(
            $bookings,
            static fn (NakkiBooking $a, NakkiBooking $b): int => $a->getStartAt() <=> $b->getStartAt(),
        );

        $nakki->setStartAt($bookings[0]->getStartAt());
        $nakki->setEndAt(end($bookings)->getEndAt());
    }

    private function intervalToHours(\DateInterval $interval): int
    {
        $hours = (int) $interval->format('%h');
        $days = (int) $interval->format('%d');

        return max(1, $hours + ($days * 24));
    }
}
