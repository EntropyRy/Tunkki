<?php

declare(strict_types=1);

namespace App\Twig\Components\Nakki;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiDefinition;
use App\Form\NakkiBoardCreateType;
use App\Repository\NakkiDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
final class Board
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(updateFromParent: true)]
    public Event $event;

    public ?string $message = null;
    public ?string $error = null;

    #[LiveProp(writable: true)]
    public array $columnIds = [];

    #[LiveProp]
    public ?NakkiDefinition $selectedDefinition = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NakkiDefinitionRepository $definitionRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->refreshEvent();
    }

    #[LiveAction]
    public function addColumn(): void
    {
        $this->error = null;
        $this->message = null;

        $this->syncSelectedDefinition();

        $form = $this->getForm();
        if (!$form->isSubmitted()) {
            $this->submitForm();
        }

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->error = $this->translator->trans('nakkikone.feedback.fix_errors').': '.implode(', ', $errors);

            return;
        }

        /** @var array{definition: ?NakkiDefinition, responsible: ?Member, mattermostChannel: ?string} $data */
        $data = $this->getForm()->getData();

        $definition = $this->selectedDefinition ?? $data['definition'];
        if (!$definition instanceof NakkiDefinition) {
            $this->error = $this->translator->trans('nakkikone.board.definition_required');

            return;
        }

        $definitionId = $definition->getId();
        if (null === $definitionId) {
            $this->error = $this->translator->trans('nakkikone.board.definition_not_persisted');

            return;
        }

        $responsible = $data['responsible'] ?? null;
        $mattermostChannel = $this->normaliseString((string) ($data['mattermostChannel'] ?? ''));

        // Check if a nakki already exists for this definition
        foreach ($this->event->getNakkis() as $existing) {
            $existingDefinition = $existing->getDefinition();
            if ($existingDefinition instanceof NakkiDefinition && $existingDefinition->getId() === $definitionId) {
                // Update existing nakki metadata
                $existing->setResponsible($responsible);
                $existing->setMattermostChannel($mattermostChannel);
                $this->entityManager->flush();
                $this->refreshEvent();

                $this->message = $this->translator->trans('nakkikone.board.column_meta_updated');

                // Reload the form with updated values
                $this->loadExistingNakkiData();

                return;
            }
        }

        // No existing nakki found - create a new one
        $nakki = new Nakki();
        $nakki->setEvent($this->event);
        $nakki->setDefinition($definition);
        $nakki->setResponsible($responsible);
        $nakki->setMattermostChannel($mattermostChannel);

        // Set default time range (use event date/time, extend by 8 hours)
        $start = \DateTimeImmutable::createFromInterface($this->event->getEventDate());
        $end = $start->modify('+8 hours');
        $nakki->setStartAt($start);
        $nakki->setEndAt($end);
        $nakki->setNakkiInterval(new \DateInterval('PT1H')); // 1 hour intervals

        $this->entityManager->persist($nakki);
        $this->entityManager->flush();
        $this->refreshEvent();

        $this->message = $this->translator->trans('nakkikone.board.column_added');

        // Reload the form with updated values
        $this->loadExistingNakkiData();
    }

    /**
     * @return list<Nakki>
     */
    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->columnIds as $id) {
            if (!\is_int($id)) {
                continue;
            }

            $nakki = $this->findNakki($id);
            if ($nakki instanceof Nakki) {
                $columns[] = $nakki;
            }
        }

        return $columns;
    }

    #[LiveListener('definition:created')]
    public function onDefinitionCreated(#[LiveArg('definitionId')] int $definitionId): void
    {
        $this->refreshEvent();

        $definition = $this->definitionRepository->find($definitionId);
        if ($definition instanceof NakkiDefinition) {
            $this->selectedDefinition = $definition;
            $this->loadExistingNakkiData();
        }

        $this->message = $this->translator->trans('nakkikone.feedback.definition_saved');
    }

    #[ExposeInTemplate(name: 'definitionsInUse')]
    public function exposeDefinitionsInUse(): array
    {
        return $this->deriveDefinitionsInUse();
    }

    public function isDefinitionInUse(): bool
    {
        if (!$this->selectedDefinition instanceof NakkiDefinition) {
            return false;
        }

        $definitionId = $this->selectedDefinition->getId();
        if (null === $definitionId) {
            return false;
        }

        $definitionsInUse = $this->deriveDefinitionsInUse();

        return \in_array((string) $definitionId, $definitionsInUse, true);
    }

    #[LiveListener('definition:selected')]
    public function onDefinitionSelected(#[LiveArg('definitionId')] ?int $definitionId): void
    {
        $this->refreshEvent();

        $this->selectedDefinition = null;
        if (null !== $definitionId) {
            $definition = $this->definitionRepository->find($definitionId);
            if ($definition instanceof NakkiDefinition) {
                $this->selectedDefinition = $definition;
            }
        }

        // Load existing data (this will reset form and populate all fields)
        $this->loadExistingNakkiData();
    }

    #[LiveListener('nakki:edit')]
    public function onColumnEdit(#[LiveArg('definitionId')] int $definitionId): void
    {
        $this->refreshEvent();

        $definition = $this->definitionRepository->find($definitionId);
        if ($definition instanceof NakkiDefinition) {
            $this->selectedDefinition = $definition;
            $this->loadExistingNakkiData();

            // Emit to Definition component to update its select dropdown
            $this->emit('board:select-definition', [
                'definitionId' => $definitionId,
            ]);
        }

        // Trigger browser scroll to planner
        $this->dispatchBrowserEvent('scroll-to-planner');
    }

    #[LiveListener('nakki:removed')]
    public function onColumnRemoved(#[LiveArg('id')] int $id): void
    {
        $this->refreshEvent();
        $this->columnIds = array_values(array_filter(
            $this->columnIds,
            static fn (int $columnId): bool => $columnId !== $id,
        ));
        $this->message = $this->translator->trans('nakkikone.board.column_removed');
    }

    protected function instantiateForm(): FormInterface
    {
        // Find existing nakki data for initial form population
        $responsible = null;
        $mattermostChannel = null;

        if ($this->selectedDefinition instanceof NakkiDefinition) {
            $definitionId = $this->selectedDefinition->getId();
            if (null !== $definitionId) {
                foreach ($this->event->getNakkis() as $nakki) {
                    $nakkiDefinition = $nakki->getDefinition();
                    if ($nakkiDefinition instanceof NakkiDefinition && $nakkiDefinition->getId() === $definitionId) {
                        $responsible = $nakki->getResponsible();
                        $mattermostChannel = $nakki->getMattermostChannel();
                        break;
                    }
                }
            }
        }

        return $this->formFactory->create(NakkiBoardCreateType::class, [
            'definition' => null,
            'responsible' => $responsible,
            'mattermostChannel' => $mattermostChannel,
        ], [
            'definitions_in_use' => $this->deriveDefinitionsInUse(),
        ]);
    }

    private function normaliseString(string $value): ?string
    {
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function refreshEvent(): void
    {
        $id = $this->event->getId();
        if (null === $id) {
            return;
        }

        // Use refresh() to force reload from database, including collections
        $this->entityManager->refresh($this->event);
        $this->columnIds = $this->deriveColumnIds();
    }

    private function syncSelectedDefinition(): void
    {
        $form = $this->getForm();
        $form->get('definition')->setData($this->selectedDefinition);

        $formName = $form->getName();
        $this->formValues[$formName] ??= [];
        $this->formValues[$formName]['definition'] = $this->selectedDefinition?->getId();
    }

    private function loadExistingNakkiData(): void
    {
        // Reset the form to recreate it with proper initial data
        $this->resetForm();

        // For autocomplete fields, we also need to update formValues
        if ($this->selectedDefinition instanceof NakkiDefinition) {
            $definitionId = $this->selectedDefinition->getId();
            if (null !== $definitionId) {
                foreach ($this->event->getNakkis() as $nakki) {
                    $nakkiDefinition = $nakki->getDefinition();
                    if ($nakkiDefinition instanceof NakkiDefinition && $nakkiDefinition->getId() === $definitionId) {
                        $formName = $this->getForm()->getName();
                        $this->formValues[$formName] ??= [];
                        $this->formValues[$formName]['mattermostChannel'] = $nakki->getMattermostChannel();
                        // Note: responsible field uses data-live-ignore, so we don't set it in formValues

                        return;
                    }
                }
            }
        }
    }

    /**
     * @return list<int>
     */
    private function deriveColumnIds(): array
    {
        $nakkis = $this->event->getNakkis()->toArray();
        usort(
            $nakkis,
            static fn (Nakki $a, Nakki $b): int => $a->getStartAt() <=> $b->getStartAt(),
        );

        return array_values(
            array_filter(
                array_map(
                    static fn (Nakki $nakki): ?int => $nakki->getId(),
                    $nakkis,
                ),
                static fn (?int $id): bool => null !== $id,
            ),
        );
    }

    private function findNakki(int $id): ?Nakki
    {
        foreach ($this->event->getNakkis() as $nakki) {
            if ($id === $nakki->getId()) {
                return $nakki;
            }
        }

        return $this->entityManager
            ->getRepository(Nakki::class)
            ->find($id);
    }

    /**
     * @return list<string>
     */
    private function deriveDefinitionsInUse(): array
    {
        $ids = [];
        foreach ($this->event->getNakkis() as $nakki) {
            $definition = $nakki->getDefinition();
            if ($definition instanceof NakkiDefinition && null !== $definition->getId()) {
                $ids[] = (string) $definition->getId();
            }
        }

        return array_values(array_unique($ids));
    }
}
