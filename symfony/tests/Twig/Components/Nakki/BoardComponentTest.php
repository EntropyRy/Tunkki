<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Factory\EventFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\Board;

final class BoardComponentTest extends LiveComponentTestCase
{
    public function testMountPopulatesColumns(): void
    {
        $event = EventFactory::new()->create();
        NakkiFactory::new()
            ->with([
                'event' => $event,
                'definition' => NakkiDefinitionFactory::new(),
            ])
            ->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        self::assertNotEmpty($board->columnIds);
        self::assertNotEmpty($board->getColumns());
    }

    public function testDefinitionEventsUpdateSelection(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();

        /** @var Board $board */
        $board = self::getContainer()->get(Board::class);
        $board->event = $event;
        $board->columnIds = [];

        $board->onDefinitionCreated($definition->getId());

        self::assertSame($definition->getId(), $board->selectedDefinition?->getId());
        self::assertNotNull($board->message);
    }
}
