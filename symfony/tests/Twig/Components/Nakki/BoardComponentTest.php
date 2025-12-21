<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkikoneFactory;
use App\Repository\EventRepository;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\Board;

final class BoardComponentTest extends LiveComponentTestCase
{
    public function testMountPopulatesColumns(): void
    {
        $event = EventFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
        NakkiFactory::new()
            ->with([
                'nakkikone' => $nakkikone,
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

    public function testAddColumnCreatesNakkiViaForm(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        $component->submitForm([
            'nakki_board_create' => [
                'definition' => $definition->getId(),
                'responsible' => null,
                'mattermostChannel' => '#crew',
            ],
        ], 'addColumn');

        $reloaded = $this->reloadEvent($event->getId());
        $nakkikone = $reloaded->getNakkikone();
        self::assertNotNull($nakkikone);
        self::assertGreaterThan(0, $nakkikone->getNakkis()->count());
    }

    public function testDefinitionSelectedLoadsExistingNakkiMetadata(): void
    {
        $event = EventFactory::new()->create();
        $member = MemberFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();

        NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'responsible' => $member,
            'mattermostChannel' => '#ops',
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->emit('definition:selected', ['definitionId' => $definition->getId()]);

        $crawler = $component->render()->crawler();
        $mattermost = $crawler->filter('input[name="nakki_board_create[mattermostChannel]"]');
        self::assertSame('#ops', $mattermost->attr('value'));
    }

    public function testIsDefinitionInUseReturnsTrueWhenSelectedMatches(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();

        NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $board->selectedDefinition = $definition;

        self::assertTrue($board->isDefinitionInUse());
    }

    public function testIsDefinitionInUseReturnsFalseForUnsavedDefinition(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $board->selectedDefinition = new \App\Entity\NakkiDefinition();

        self::assertFalse($board->isDefinitionInUse());
    }

    public function testOnColumnEditSelectsDefinitionAndPrefillsForm(): void
    {
        $event = EventFactory::new()->create();
        $member = MemberFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();

        NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'responsible' => $member,
            'mattermostChannel' => '#crew',
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->emit('nakki:edit', ['definitionId' => $definition->getId()]);

        $crawler = $component->render()->crawler();
        $mattermost = $crawler->filter('input[name="nakki_board_create[mattermostChannel]"]');
        self::assertSame('#crew', $mattermost->attr('value'));
    }

    public function testOnColumnRemovedUpdatesColumnIds(): void
    {
        $event = EventFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
        $first = NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => NakkiDefinitionFactory::new(),
        ])->create();
        $second = NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => NakkiDefinitionFactory::new(),
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $board->columnIds = [$first->getId(), $second->getId()];

        $board->onColumnRemoved($first->getId());

        self::assertSame([$second->getId()], $board->columnIds);
        self::assertNotNull($board->message);
    }

    public function testGetColumnsFallsBackToRepository(): void
    {
        $event = EventFactory::new()->create();
        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        $otherEvent = EventFactory::new()->create();
        $otherNakkikone = NakkikoneFactory::new()->with(['event' => $otherEvent])->create();
        $otherNakki = NakkiFactory::new()->with([
            'nakkikone' => $otherNakkikone,
            'definition' => NakkiDefinitionFactory::new(),
        ])->create();

        /** @var Board $board */
        $board = $component->component();
        $board->columnIds = [$otherNakki->getId()];

        $columns = $board->getColumns();
        self::assertCount(1, $columns);
        self::assertSame($otherNakki->getId(), $columns[0]->getId());
    }

    private function reloadEvent(int $id)
    {
        /** @var EventRepository $repository */
        $repository = self::getContainer()->get(EventRepository::class);

        $event = $repository->find($id);
        self::assertNotNull($event);

        return $event;
    }
}
