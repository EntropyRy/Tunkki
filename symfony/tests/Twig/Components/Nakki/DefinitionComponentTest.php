<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Entity\NakkiDefinition;
use App\Factory\EventFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkikoneFactory;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\Definition;
use Doctrine\ORM\EntityManagerInterface;

final class DefinitionComponentTest extends LiveComponentTestCase
{
    public function testSelectedDefinitionIdUpdateAppliesDefinition(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        /** @var Definition $definition */
        $definition = $component->component();
        $definition->selectedDefinitionId = $definitionEntity->getId();
        $definition->onSelectedDefinitionIdUpdated();

        self::assertSame($definitionEntity->getId(), $definition->selectedDefinitionId);
        self::assertSame($definitionEntity->getId(), $definition->selectedDefinition?->getId());
        self::assertFalse($definition->showForm);
    }

    public function testFormDefinitionIdUpdateHydratesAndShowsForm(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        /** @var Definition $definition */
        $definition = $component->component();
        $definition->formDefinitionId = $definitionEntity->getId();
        $definition->onFormDefinitionIdUpdated();

        self::assertTrue($definition->showForm);
        self::assertSame($definitionEntity->getId(), $definition->formDefinition?->getId());
    }

    public function testFormDefinitionIdUpdateKeepsFormOpenWhenNull(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        /** @var Definition $definition */
        $definition = $component->component();
        $definition->showForm = true;
        $definition->formDefinitionId = null;
        $definition->onFormDefinitionIdUpdated();

        self::assertTrue($definition->showForm);
        self::assertNull($definition->formDefinition);
    }

    public function testCreateDefinitionClosesFormWhenNoDefinitionLoaded(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        /** @var Definition $definition */
        $definition = $component->component();
        $definition->showForm = true;
        $definition->formDefinition = null;
        $definition->createDefinition();

        self::assertFalse($definition->showForm);
    }

    public function testCreateDefinitionTogglesFormVisibility(): void
    {
        $event = EventFactory::new()->create();
        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        $component->call('createDefinition');
        /** @var Definition $definition */
        $definition = $component->component();
        self::assertTrue($definition->showForm);
    }

    public function testSelectDefinitionStoresIdentifier(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        $component->call('selectDefinition', ['definitionId' => $definitionEntity->getId()]);
        /** @var Definition $definition */
        $definition = $component->component();
        self::assertSame($definitionEntity->getId(), $definition->selectedDefinitionId);

        $component->emit('definition:created', ['definitionId' => $definitionEntity->getId()]);
        $updated = $component->component();
        self::assertFalse($updated->showForm);
        self::assertSame($definitionEntity->getId(), $updated->selectedDefinitionId);
    }

    public function testEditDefinitionTogglesForm(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        $component->call('editDefinition', ['definitionId' => $definitionEntity->getId()]);
        /** @var Definition $definition */
        $definition = $component->component();
        self::assertTrue($definition->showForm);
        self::assertSame($definitionEntity->getId(), $definition->formDefinitionId);

        $component->call('closeForm');
        $definition = $component->component();
        self::assertFalse($definition->showForm);
    }

    public function testEditDefinitionIgnoresUnknownDefinition(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        $component->call('editDefinition', ['definitionId' => 999999]);
        /** @var Definition $definition */
        $definition = $component->component();
        self::assertFalse($definition->showForm);
        self::assertNull($definition->formDefinition);
    }

    public function testEditDefinitionTogglesOffWhenSameDefinitionSelected(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        /** @var Definition $definition */
        $definition = $component->component();
        $definition->showForm = true;
        $definition->formDefinition = $definitionEntity;
        $definition->formDefinitionId = $definitionEntity->getId();
        $definition->editDefinition($definitionEntity->getId());

        self::assertFalse($definition->showForm);
        self::assertNull($definition->formDefinition);
        self::assertNull($definition->formDefinitionId);
    }

    public function testBoardSelectDefinitionResetsFormState(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();

        /** @var Definition $definition */
        $definition = $component->component();
        $definition->showForm = true;
        $definition->formDefinition = $definitionEntity;
        $definition->formDefinitionId = $definitionEntity->getId();

        $definition->onBoardSelectDefinition($definitionEntity->getId());

        self::assertSame($definitionEntity->getId(), $definition->selectedDefinitionId);
        self::assertSame($definitionEntity->getId(), $definition->selectedDefinition?->getId());
        self::assertFalse($definition->showForm);
        self::assertNull($definition->formDefinition);
        self::assertNull($definition->formDefinitionId);
    }

    public function testUsageExamplesListsRecentEvents(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
        NakkiFactory::new()
            ->with([
                'nakkikone' => $nakkikone,
                'definition' => $definitionEntity,
            ])
            ->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();
        /** @var Definition $definition */
        $definition = $component->component();

        $examples = $definition->getUsageExamples($definitionEntity, 'fi');
        self::assertIsArray($examples);
    }

    public function testUsageExamplesReturnsEmptyForNullDefinition(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Definition::class, ['event' => $event]);
        $component->render();
        /** @var Definition $definition */
        $definition = $component->component();

        self::assertSame([], $definition->getUsageExamples(null, 'fi'));
    }

    public function testUsageExamplesCachesAndLimitsToThree(): void
    {
        $definitionEntity = NakkiDefinitionFactory::new()->create();
        $events = [
            EventFactory::new()->create(['eventDate' => new \DateTimeImmutable('2025-01-10 18:00:00')]),
            EventFactory::new()->create(['eventDate' => new \DateTimeImmutable('2025-01-09 18:00:00')]),
            EventFactory::new()->create(['eventDate' => new \DateTimeImmutable('2025-01-08 18:00:00')]),
            EventFactory::new()->create(['eventDate' => new \DateTimeImmutable('2025-01-07 18:00:00')]),
        ];

        foreach ($events as $event) {
            $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
            NakkiFactory::new()->with([
                'nakkikone' => $nakkikone,
                'definition' => $definitionEntity,
            ])->create();
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
        $entityManager->clear();

        $definitionEntity = $entityManager
            ->getRepository(NakkiDefinition::class)
            ->find($definitionEntity->getId());
        self::assertInstanceOf(NakkiDefinition::class, $definitionEntity);

        $component = $this->mountComponent(Definition::class, ['event' => $events[0]]);
        $component->render();
        /** @var Definition $definition */
        $definition = $component->component();

        $first = $definition->getUsageExamples($definitionEntity, 'fi');
        $second = $definition->getUsageExamples($definitionEntity, 'fi');

        self::assertCount(3, $first);
        self::assertSame($first, $second);
    }

    public function testDefinitionCreatedReloadsDetachedEvent(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var Definition $definition */
        $definition = self::getContainer()->get(Definition::class);
        $definition->event = $event;
        $definition->onDefinitionCreated($definitionEntity->getId());
        self::assertNotSame($event, $definition->event);
    }
}
