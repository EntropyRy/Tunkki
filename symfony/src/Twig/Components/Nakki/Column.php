<?php

declare(strict_types=1);

namespace App\Twig\Components\Nakki;

use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
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
    public function addSlots(): void
    {
        $this->notice = null;
        $this->error = null;

        $count = max(1, $this->newSlotCount);
        $intervalHours = max(1, $this->newSlotIntervalHours);
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
    public function saveDisable(): void
    {
        $nakki = $this->getNakki();
        $nakki->setDisableBookings($this->disableBookings);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans($this->disableBookings ? 'nakkikone.column.disabled' : 'nakkikone.column.enabled');
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
            $key = $booking->getStartAt()->format('c');
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
