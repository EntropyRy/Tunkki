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
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Test\Factories;

final class NakkiCreateFormTest extends FixturesWebTestCase
{
    use Factories;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = self::getContainer();
    }

    public function testSaveCreatesNakki(): void
    {
        $component = $this->component();
        $eventProxy = EventFactory::new()->create();
        $definitionProxy = NakkiDefinitionFactory::new()->create();
        $memberProxy = MemberFactory::new()->create();
        $event = $eventProxy instanceof Proxy ? $eventProxy->_real() : $eventProxy;
        $definition = $definitionProxy instanceof Proxy ? $definitionProxy->_real() : $definitionProxy;
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;

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

        // Reload event in the current EntityManager to see changes made by component.
        $event = $this->container
            ->get(EventRepository::class)
            ->find($event->getId());
        self::assertNotNull($event);

        $nakkikone = $event->getNakkikone();
        self::assertNotNull($nakkikone, 'Event should have nakkikone created');
        self::assertNotEmpty($repository->findBy(['nakkikone' => $nakkikone]));
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

    public function testSaveRequiresEvent(): void
    {
        $component = $this->component();
        $component->eventId = \PHP_INT_MAX;
        $component->definitionId = 123;
        $component->startAt = '2025-01-01T10:00';
        $component->endAt = '2025-01-01T12:00';
        $component->intervalHours = 1;

        $component->save();

        self::assertSame('Event not found.', $component->error);
    }

    public function testSaveRequiresDefinition(): void
    {
        $component = $this->component();
        $event = EventFactory::new()->create();

        $component->eventId = $event->getId();
        $component->definitionId = null;
        $component->startAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->endAt = $event->getEventDate()->modify('+2 hours')->format('Y-m-d\TH:i');
        $component->intervalHours = 1;

        $component->save();

        self::assertSame('Definition is required.', $component->error);
    }

    public function testSaveValidatesDefinitionExists(): void
    {
        $component = $this->component();
        $event = EventFactory::new()->create();

        $component->eventId = $event->getId();
        $component->definitionId = \PHP_INT_MAX;
        $component->startAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->endAt = $event->getEventDate()->modify('+2 hours')->format('Y-m-d\TH:i');
        $component->intervalHours = 1;

        $component->save();

        self::assertSame('Definition not found.', $component->error);
    }

    public function testSaveRequiresStartAndEndTimes(): void
    {
        $component = $this->component();
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();

        $component->eventId = $event->getId();
        $component->definitionId = $definition->getId();
        $component->startAt = '';
        $component->endAt = '';
        $component->intervalHours = 1;

        $component->save();

        self::assertSame('Start and end times are required.', $component->error);
    }

    public function testSaveValidatesIntervalHours(): void
    {
        $component = $this->component();
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create();

        $component->eventId = $event->getId();
        $component->definitionId = $definition->getId();
        $component->startAt = $event->getEventDate()->format('Y-m-d\TH:i');
        $component->endAt = $event->getEventDate()->modify('+2 hours')->format('Y-m-d\TH:i');
        $component->intervalHours = 0;

        $component->save();

        self::assertSame('Interval must be at least one hour.', $component->error);
    }

    public function testMountSeedsDefaultTimesAndDefinition(): void
    {
        $event = EventFactory::new()->create();
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'AAA']);
        NakkiDefinitionFactory::new()->create(['nameFi' => 'ZZZ']);

        $component = $this->component();
        $component->eventId = $event->getId();
        $component->definitionId = null;

        $component->mount();

        self::assertSame($event->getEventDate()->format('Y-m-d\\TH:i'), $component->startAt);
        self::assertSame($event->getEventDate()->modify('+1 hour')->format('Y-m-d\\TH:i'), $component->endAt);
        self::assertSame($definition->getId(), $component->definitionId);
    }

    public function testMountShowsErrorWhenEventMissing(): void
    {
        $component = $this->component();
        $component->eventId = \PHP_INT_MAX;

        $component->mount();

        self::assertSame('Event not found.', $component->error);
    }

    public function testOnDefinitionCreatedUpdatesDefinitionId(): void
    {
        $component = $this->component();

        $component->onDefinitionCreated(123);

        self::assertSame(123, $component->definitionId);
    }

    public function testGetDefinitionsReturnsOrderedDefinitions(): void
    {
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'AAA']);
        NakkiDefinitionFactory::new()->create(['nameFi' => 'ZZZ']);

        $component = $this->component();
        $definitions = $component->getDefinitions();

        self::assertSame($definition->getId(), $definitions[0]->getId());
    }

    public function testGetMembersReturnsOrderedMembers(): void
    {
        $member = MemberFactory::new()->create(['lastname' => 'Aaa', 'firstname' => 'Bbb']);
        MemberFactory::new()->create(['lastname' => 'Zzz', 'firstname' => 'Aaa']);

        $component = $this->component();
        $members = $component->getMembers();

        self::assertSame($member->getId(), $members[0]->getId());
    }

    public function testNormaliseStringReturnsNullForBlank(): void
    {
        $component = $this->component();
        $method = new \ReflectionMethod(NakkiCreateForm::class, 'normaliseString');
        $method->setAccessible(true);

        self::assertNull($method->invoke($component, null));
        self::assertNull($method->invoke($component, '   '));
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
