<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Nakki;

use App\Factory\EventFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Repository\NakkiRepository;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Nakki\NakkiCreateForm;

final class NakkiCreateFormComponentTest extends LiveComponentTestCase
{
    public function testSaveCreatesNakkiWithBookings(): void
    {
        $event = EventFactory::new()->published()->create();
        $definition = NakkiDefinitionFactory::new()->create();

        /** @var NakkiCreateForm $component */
        $component = self::getContainer()->get(NakkiCreateForm::class);
        $component->eventId = $event->getId();
        $component->definitionId = $definition->getId();
        $component->startAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->endAt = $event->getEventDate()->modify('+2 hours')->format('Y-m-d\TH:i');
        $component->intervalHours = 1;
        $component->mattermostChannel = '#crew';

        $component->save();

        self::assertNotNull($component->notice);

        /** @var NakkiRepository $nakkiRepository */
        $nakkiRepository = self::getContainer()->get(NakkiRepository::class);
        $nakkis = $nakkiRepository->findBy(['event' => $event]);
        self::assertNotEmpty($nakkis, 'Expected newly created nakki for the event.');
    }
}
