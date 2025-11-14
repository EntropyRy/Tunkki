<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nakki;

use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Repository\EventRepository;
use App\Repository\MemberRepository;
use App\Repository\NakkiDefinitionRepository;
use App\Repository\NakkiRepository;
use App\Service\NakkiScheduler;
use App\Tests\_Base\FixturesWebTestCase;
use App\Twig\Components\Nakki\NakkiCreateForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\UX\LiveComponent\LiveResponder;

final class NakkiCreateFormTest extends FixturesWebTestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = self::getContainer();
    }

    public function testSaveCreatesNakki(): void
    {
        $component = $this->component();
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();
        $member = MemberFactory::new()->create();

        $component->eventId = $event->getId();
        $component->definitionId = $definition->getId();
        $component->startAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->endAt = $event->getEventDate()->modify('+2 hours')->format('Y-m-d\TH:i');
        $component->intervalHours = 1;
        $component->responsibleId = $member->getId();
        $component->mattermostChannel = '#crew';

        $component->save();

        self::assertNull($component->error);
        self::assertNotNull($component->notice);

        /** @var NakkiRepository $repository */
        $repository = $this->container->get(NakkiRepository::class);
        self::assertNotEmpty($repository->findBy(['event' => $event]));
    }

    public function testSaveValidatesIntervals(): void
    {
        $component = $this->component();
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();

        $component->eventId = $event->getId();
        $component->definitionId = $definition->getId();
        $component->startAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->endAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->intervalHours = 0;

        $component->save();

        self::assertSame('End time must be after start time.', $component->error);
    }

    private function component(): NakkiCreateForm
    {
        $component = new NakkiCreateForm(
            $this->container->get(EventRepository::class),
            $this->container->get(NakkiDefinitionRepository::class),
            $this->container->get(MemberRepository::class),
            $this->container->get(EntityManagerInterface::class),
            $this->container->get(NakkiScheduler::class),
        );

        $component->setLiveResponder(new LiveResponder());

        return $component;
    }
}
