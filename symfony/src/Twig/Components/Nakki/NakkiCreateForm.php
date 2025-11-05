<?php

declare(strict_types=1);

namespace App\Twig\Components\Nakki;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiDefinition;
use App\Repository\EventRepository;
use App\Repository\MemberRepository;
use App\Repository\NakkiDefinitionRepository;
use App\Service\NakkiScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Nakki:NakkiCreateForm')]
final class NakkiCreateForm
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public int $eventId;

    #[LiveProp(writable: true)]
    public ?int $definitionId = null;

    #[LiveProp(writable: true)]
    public string $startAt = '';

    #[LiveProp(writable: true)]
    public string $endAt = '';

    #[LiveProp(writable: true)]
    public int $intervalHours = 1;

    #[LiveProp(writable: true)]
    public ?int $responsibleId = null;

    #[LiveProp(writable: true)]
    public string $mattermostChannel = '';

    public ?string $notice = null;
    public ?string $error = null;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly NakkiDefinitionRepository $definitionRepository,
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NakkiScheduler $scheduler,
    ) {
    }

    public function mount(): void
    {
        $event = $this->eventRepository->find($this->eventId);
        if (!$event instanceof Event) {
            $this->error = 'Event not found.';

            return;
        }

        $start = \DateTimeImmutable::createFromInterface($event->getEventDate());
        $end = $start->modify('+1 hour');

        $this->startAt = $start->format('Y-m-d\TH:i');
        $this->endAt = $end->format('Y-m-d\TH:i');

        if (null === $this->definitionId) {
            $firstDefinition = $this->definitionRepository->findOneBy([], ['nameFi' => 'ASC']);
            $this->definitionId = $firstDefinition?->getId();
        }
    }

    #[LiveAction]
    public function save(): void
    {
        $this->notice = null;
        $this->error = null;

        $event = $this->eventRepository->find($this->eventId);
        if (!$event instanceof Event) {
            $this->error = 'Event not found.';

            return;
        }

        if (null === $this->definitionId) {
            $this->error = 'Definition is required.';

            return;
        }

        $definition = $this->definitionRepository->find($this->definitionId);
        if (!$definition instanceof NakkiDefinition) {
            $this->error = 'Definition not found.';

            return;
        }

        $start = $this->parseDateTime($this->startAt);
        $end = $this->parseDateTime($this->endAt);
        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            $this->error = 'Start and end times are required.';

            return;
        }

        if ($start >= $end) {
            $this->error = 'End time must be after start time.';

            return;
        }

        if ($this->intervalHours <= 0) {
            $this->error = 'Interval must be at least one hour.';

            return;
        }

        $interval = new \DateInterval(\sprintf('PT%dH', $this->intervalHours));

        $nakki = new Nakki();
        $nakki->setEvent($event);
        $nakki->setDefinition($definition);
        $nakki->setStartAt($start);
        $nakki->setEndAt($end);
        $nakki->setNakkiInterval($interval);
        $nakki->setMattermostChannel($this->normaliseString($this->mattermostChannel));

        if (null !== $this->responsibleId) {
            $responsible = $this->memberRepository->find($this->responsibleId);
            if ($responsible instanceof Member) {
                $nakki->setResponsible($responsible);
            }
        }

        $this->entityManager->persist($nakki);
        $result = $this->scheduler->initialise($nakki);
        $this->entityManager->flush();

        $this->emit('nakki:created', ['nakkiId' => $nakki->getId()]);

        $this->notice = \sprintf('Created %d booking(s).', \count($result->created));

        $this->resetForm($nakki);
    }

    #[LiveListener('definition:created')]
    public function onDefinitionCreated(#[LiveArg('definitionId')] ?int $definitionId = null): void
    {
        if (null !== $definitionId) {
            $this->definitionId = $definitionId;
        }
    }

    public function getDefinitions(): array
    {
        return $this->definitionRepository->findBy([], ['nameFi' => 'ASC']);
    }

    /**
     * @return list<Member>
     */
    public function getMembers(): array
    {
        return $this->memberRepository->findBy([], ['lastname' => 'ASC', 'firstname' => 'ASC']);
    }

    private function resetForm(Nakki $nakki): void
    {
        $event = $nakki->getEvent();
        $this->definitionId = $nakki->getDefinition()->getId();
        $start = \DateTimeImmutable::createFromInterface($event->getEventDate());
        $end = $start->add(new \DateInterval('PT1H'));
        $this->startAt = $start->format('Y-m-d\TH:i');
        $this->endAt = $end->format('Y-m-d\TH:i');
        $this->intervalHours = 1;
        $this->responsibleId = null;
        $this->mattermostChannel = '';
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if ('' === trim($value)) {
            return null;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);

        return false === $dateTime ? null : $dateTime;
    }

    private function normaliseString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
