<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Factory\NakkiDefinitionFactory;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\DefinitionForm;

final class DefinitionFormComponentTest extends LiveComponentTestCase
{
    public function testNewDefinitionActivatesForm(): void
    {
        /** @var DefinitionForm $component */
        $component = self::getContainer()->get(DefinitionForm::class);
        $component->newDefinition();

        self::assertTrue($component->active);
        self::assertNull($component->definitionId);
        self::assertNull($component->error);
    }

    public function testEditDefinitionLoadsExistingData(): void
    {
        $definition = NakkiDefinitionFactory::new()
            ->with(['nameFi' => 'Existing', 'nameEn' => 'Existing'])
            ->create();

        /** @var DefinitionForm $component */
        $component = self::getContainer()->get(DefinitionForm::class);
        $component->editDefinition(definitionId: $definition->getId());

        self::assertSame($definition->getId(), $component->definitionId);
        self::assertTrue($component->active);
    }
}
