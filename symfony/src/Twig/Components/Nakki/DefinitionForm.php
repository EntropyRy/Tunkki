<?php

declare(strict_types=1);

namespace App\Twig\Components\Nakki;

use App\Entity\NakkiDefinition;
use App\Form\NakkiDefinitionType;
use App\Repository\NakkiDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Nakki:DefinitionForm')]
final class DefinitionForm
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true, onUpdated: 'definitionIdUpdated')]
    public ?int $definitionId = null;

    #[LiveProp(writable: true)]
    public bool $active = false;

    public ?string $notice = null;
    public ?string $error = null;

    private ?NakkiDefinition $definition = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly NakkiDefinitionRepository $definitionRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[LiveAction]
    public function newDefinition(): void
    {
        $this->definitionId = null;
        $this->active = true;
        $this->notice = null;
        $this->error = null;
        $this->definition = null;
        $this->resetForm();
    }

    #[LiveAction]
    public function editDefinition(#[LiveArg('definitionId')] int $definitionId): void
    {
        $this->definitionId = $definitionId;
        $this->active = true;
        $this->notice = null;
        $this->error = null;
        $this->definition = null;
        $this->resetForm();
    }

    public function definitionIdUpdated(): void
    {
        $this->notice = null;
        $this->error = null;
        $this->definition = null;
        $this->resetForm();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->error = null;
        $this->notice = null;

        $this->submitForm();

        $definition = $this->resolveDefinition();
        $this->entityManager->persist($definition);
        $this->entityManager->flush();

        $this->definitionId = $definition->getId();
        $this->notice = $this->translator->trans('nakkikone.feedback.definition_saved');
        $this->emit('definition:created', [
            'definitionId' => $definition->getId(),
        ]);
        $this->resetForm();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(NakkiDefinitionType::class, $this->resolveDefinition());
    }

    private function resolveDefinition(): NakkiDefinition
    {
        if ($this->definition instanceof NakkiDefinition) {
            return $this->definition;
        }

        if (null === $this->definitionId) {
            $this->definition = new NakkiDefinition();

            return $this->definition;
        }

        $definition = $this->definitionRepository->find($this->definitionId);
        if (!$definition instanceof NakkiDefinition) {
            $this->definitionId = null;
            $this->definition = new NakkiDefinition();

            return $this->definition;
        }

        return $this->definition = $definition;
    }
}
