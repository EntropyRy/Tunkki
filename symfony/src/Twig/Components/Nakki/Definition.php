<?php

declare(strict_types=1);

namespace App\Twig\Components\Nakki;

use App\Entity\Event;
use App\Entity\Nakki;
use App\Entity\NakkiDefinition;
use App\Repository\NakkiDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Nakki:Definition')]
final class Definition
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp(updateFromParent: true)]
    public Event $event;

    #[LiveProp]
    public ?NakkiDefinition $selectedDefinition = null;

    #[LiveProp(writable: true, onUpdated: 'onSelectedDefinitionIdUpdated')]
    public ?int $selectedDefinitionId = null;

    #[LiveProp(writable: true)]
    public bool $showForm = false;

    #[LiveProp]
    public ?NakkiDefinition $formDefinition = null;

    #[LiveProp(writable: true, onUpdated: 'onFormDefinitionIdUpdated')]
    public ?int $formDefinitionId = null;

    /**
     * Cache of usage examples keyed by "<definitionId>|<locale>".
     *
     * @var array<string, list<string>>
     */
    private array $usageCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NakkiDefinitionRepository $definitionRepository,
    ) {
    }

    public function mount(): void
    {
        $this->selectedDefinitionId = $this->selectedDefinition?->getId();
        $this->formDefinitionId = $this->formDefinition?->getId();
    }

    public function getDefinitions(): array
    {
        $definitions = $this->definitionRepository->findBy([], ['nameFi' => 'ASC', 'nameEn' => 'ASC']);
        $usedIds = $this->collectEventDefinitionIds();

        $result = [];
        foreach ($definitions as $definition) {
            \assert($definition instanceof NakkiDefinition);
            $id = $definition->getId();
            $result[] = [
                'definition' => $definition,
                'usedInEvent' => null !== $id && isset($usedIds[$id]),
            ];
        }

        return $result;
    }

    public function onSelectedDefinitionIdUpdated(): void
    {
        $this->applySelectedDefinition($this->selectedDefinitionId);
    }

    public function onFormDefinitionIdUpdated(): void
    {
        $this->formDefinition = $this->hydrateDefinition($this->formDefinitionId);
        if (null !== $this->formDefinitionId) {
            $this->showForm = true;
        }
    }

    #[LiveAction]
    public function selectDefinition(#[LiveArg('definitionId')] ?int $definitionId): void
    {
        $this->applySelectedDefinition($definitionId);
    }

    #[LiveAction]
    public function createDefinition(): void
    {
        if ($this->showForm && !$this->formDefinition instanceof NakkiDefinition) {
            $this->showForm = false;

            return;
        }

        $this->formDefinition = null;
        $this->formDefinitionId = null;
        $this->showForm = true;
    }

    #[LiveAction]
    public function editDefinition(#[LiveArg('definitionId')] int $definitionId): void
    {
        $definition = $this->hydrateDefinition($definitionId);
        if (!$definition instanceof NakkiDefinition) {
            return;
        }

        if ($this->showForm && $this->formDefinition instanceof NakkiDefinition && $this->formDefinition->getId() === $definitionId) {
            $this->showForm = false;
            $this->formDefinition = null;
            $this->formDefinitionId = null;

            return;
        }

        $this->formDefinition = $definition;
        $this->formDefinitionId = $definitionId;
        $this->showForm = true;
    }

    #[LiveAction]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formDefinition = null;
        $this->formDefinitionId = null;
    }

    #[LiveListener('definition:created')]
    public function onDefinitionCreated(#[LiveArg('definitionId')] int $definitionId): void
    {
        $this->refreshEvent();

        $definition = $this->hydrateDefinition($definitionId);
        $this->selectedDefinition = $definition;
        $this->selectedDefinitionId = $definition?->getId();
        $this->formDefinition = $definition;
        $this->formDefinitionId = $definition?->getId();
        $this->showForm = false;

        $this->emitUp('definition:selected', [
            'definitionId' => $definition?->getId(),
        ]);
    }

    #[LiveListener('board:select-definition')]
    public function onBoardSelectDefinition(#[LiveArg('definitionId')] int $definitionId): void
    {
        // When Board tells us to select a definition, just update our state
        // Don't emit back to Board (it already knows!)
        $definition = $this->hydrateDefinition($definitionId);
        $this->selectedDefinition = $definition;
        $this->selectedDefinitionId = $definition?->getId();
        $this->showForm = false;
        $this->formDefinition = null;
        $this->formDefinitionId = null;

        // NO emitUp here - Board is already handling the selection
    }

    /**
     * @return list<string>
     */
    public function getUsageExamples(?NakkiDefinition $definition, string $locale): array
    {
        if (!$definition instanceof NakkiDefinition || null === $definition->getId()) {
            return [];
        }

        $cacheKey = $definition->getId().'|'.$locale;
        if (!isset($this->usageCache[$cacheKey])) {
            $nakkis = $this->entityManager
                ->getRepository(Nakki::class)
                ->findBy(['definition' => $definition]);

            $eventsById = [];
            foreach ($nakkis as $nakki) {
                $event = $nakki->getEvent();
                if ($event instanceof Event && null !== $event->getId()) {
                    $eventsById[$event->getId()] = $event;
                }
            }

            $events = array_values($eventsById);
            usort($events, static fn (Event $a, Event $b): int => $b->getEventDate() <=> $a->getEventDate());

            $names = [];
            foreach (\array_slice($events, 0, 3) as $event) {
                $names[] = $event->getNameByLang($locale);
            }

            $this->usageCache[$cacheKey] = $names;
        }

        return $this->usageCache[$cacheKey];
    }

    private function collectEventDefinitionIds(): array
    {
        $ids = [];
        foreach ($this->event->getNakkis() as $nakki) {
            $definition = $nakki->getDefinition();
            if (null === $definition) {
                continue;
            }
            $id = $definition->getId();
            if (null !== $id) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function refreshEvent(): void
    {
        try {
            $this->entityManager->refresh($this->event);
        } catch (\Throwable) {
            if (null !== $this->event->getId()) {
                $managed = $this->entityManager
                    ->getRepository(Event::class)
                    ->find($this->event->getId());
                if ($managed instanceof Event) {
                    $this->event = $managed;
                }
            }
        }
    }

    private function applySelectedDefinition(?int $definitionId): void
    {
        $definition = $this->hydrateDefinition($definitionId);
        $previousId = $this->selectedDefinition?->getId();
        $newId = $definition?->getId();

        $this->selectedDefinition = $definition;
        $this->selectedDefinitionId = $newId;
        $this->showForm = false;
        $this->formDefinition = null;
        $this->formDefinitionId = null;

        // Only emit if the selection actually changed
        if ($previousId !== $newId) {
            $this->emitUp('definition:selected', [
                'definitionId' => $newId,
            ]);
        }
    }

    private function hydrateDefinition(?int $definitionId): ?NakkiDefinition
    {
        if (null === $definitionId) {
            return null;
        }

        return $this->definitionRepository->find($definitionId);
    }
}
