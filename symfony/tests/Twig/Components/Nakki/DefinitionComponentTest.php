<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Factory\EventFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\Definition;

final class DefinitionComponentTest extends LiveComponentTestCase
{
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

    public function testUsageExamplesListsRecentEvents(): void
    {
        $event = EventFactory::new()->create();
        $definitionEntity = NakkiDefinitionFactory::new()->create();
        NakkiFactory::new()
            ->with([
                'event' => $event,
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
}
