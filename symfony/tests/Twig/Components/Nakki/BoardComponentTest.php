<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiBookingFactory;
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

    public function testIsDefinitionInUseReturnsFalseWhenNoDefinitionSelected(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $board->selectedDefinition = null;

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

    public function testToggleScheduleRendersCombinedScheduleTable(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Aikataulu',
        ]);
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
        $nakki = NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
        ])->create();
        NakkiBookingFactory::new()->with([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->call('toggleSchedule');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.nakkikone-schedule-table')->count());
        self::assertSame(0, $crawler->filter('#nakkikone-planner')->count());
        self::assertSame(0, $crawler->filter('#nakki-definition-select')->count());
    }

    public function testScheduleGridReturnsEmptyWithoutNakkikone(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $grid = $board->getScheduleGrid();

        self::assertSame([], $grid['timeSlots']);
        self::assertSame(1, $grid['maxColumns']);
    }

    public function testScheduleGridReturnsEmptyWithoutBookings(): void
    {
        $event = EventFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
        NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => NakkiDefinitionFactory::new(),
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $grid = $board->getScheduleGrid();

        self::assertSame([], $grid['timeSlots']);
        self::assertSame(1, $grid['maxColumns']);
    }

    public function testScheduleGridHandlesOverlapsAndHourBoundaries(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();
        $nakki = NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
        ])->create();

        $booking1Start = new \DateTimeImmutable('2025-01-01 10:00:00');
        $booking1End = $booking1Start->modify('+2 hours');
        $booking2Start = new \DateTimeImmutable('2025-01-01 11:00:00');
        $booking2End = $booking2Start->modify('+90 minutes');

        NakkiBookingFactory::new()->with([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $booking1Start,
            'endAt' => $booking1End,
        ])->create();
        NakkiBookingFactory::new()->with([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $booking2Start,
            'endAt' => $booking2End,
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $grid = $board->getScheduleGrid();

        self::assertSame(2, $grid['maxColumns']);
        self::assertCount(3, $grid['timeSlots']);

        $hasPlaceholder = false;
        foreach ($grid['timeSlots'] as $slot) {
            foreach ($slot['bookings'] as $bookingData) {
                if (null === $bookingData['booking']) {
                    $hasPlaceholder = true;
                    break 2;
                }
            }
        }
        self::assertTrue($hasPlaceholder);
    }

    public function testAddColumnWithoutDefinitionShowsError(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->submitForm([
            'nakki_board_create' => [],
        ], 'addColumn');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert-warning')->count());
    }

    public function testAddColumnShowsErrorWhenDefinitionIsInvalid(): void
    {
        $event = EventFactory::new()->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class);
        $component->submitForm([
            'nakki_board_create' => [
                'definition' => 'invalid',
            ],
        ], 'addColumn');
    }

    public function testAddColumnUpdatesExistingNakkiMetadata(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();
        $member = MemberFactory::new()->create();
        $nakkikone = NakkikoneFactory::new()->with(['event' => $event])->create();

        NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'mattermostChannel' => '#old',
        ])->create();

        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->submitForm([
            'nakki_board_create' => [
                'definition' => $definition->getId(),
                'responsible' => $member->getId(),
                'mattermostChannel' => '#new',
            ],
        ], 'addColumn');

        $reloaded = $this->reloadEvent($event->getId());
        $reloadedNakkikone = $reloaded->getNakkikone();
        self::assertNotNull($reloadedNakkikone);
        self::assertSame(1, $reloadedNakkikone->getNakkis()->count());

        $nakki = $reloadedNakkikone->getNakkis()->first();
        self::assertSame('#new', $nakki->getMattermostChannel());
        self::assertSame($member->getId(), $nakki->getResponsible()?->getId());
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

    public function testGetColumnsSkipsNonIntegerIds(): void
    {
        $event = EventFactory::new()->create();
        $component = $this->mountComponent(Board::class, ['event' => $event]);
        $component->render();

        /** @var Board $board */
        $board = $component->component();
        $board->columnIds = ['1'];

        self::assertSame([], $board->getColumns());
    }

    public function testMountDoesNotRefreshWhenEventIsNotPersisted(): void
    {
        $event = new Event();

        /** @var Board $board */
        $board = self::getContainer()->get(Board::class);
        $board->mount($event);

        self::assertSame([], $board->columnIds);
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
