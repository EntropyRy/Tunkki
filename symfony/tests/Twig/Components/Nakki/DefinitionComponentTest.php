<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Factory\EventFactory;
use App\Factory\NakkiDefinitionFactory;
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
}
