<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Admin\NakkiAdmin;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
use App\Entity\Nakkikone;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Persistence\Proxy;

#[Group('admin')]
#[Group('nakki')]
final class NakkiAdminTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testBaseRoutePatternIsNakki(): void
    {
        self::assertSame('nakki', $this->nakkiAdmin()->getBaseRoutePattern());
    }

    public function testDatagridFiltersIncludeEventForStandalone(): void
    {
        $admin = $this->standaloneAdmin();
        $datagrid = $admin->getDatagrid();

        self::assertTrue($datagrid->hasFilter('definition'));
        self::assertTrue($datagrid->hasFilter('event'));
        self::assertTrue($datagrid->hasFilter('responsible'));
        self::assertTrue($datagrid->hasFilter('startAt'));
        self::assertTrue($datagrid->hasFilter('endAt'));
        self::assertTrue($datagrid->hasFilter('disableBookings'));
    }

    public function testDatagridFiltersIncludeParentEventForChild(): void
    {
        $admin = $this->childAdmin();
        self::assertTrue($admin->isChild());
        $datagrid = $admin->getDatagrid();

        self::assertTrue($datagrid->hasFilter('event'));
        self::assertTrue($datagrid->hasFilter('definition'));
    }

    public function testListFieldsIncludeEventForStandalone(): void
    {
        $list = $this->standaloneAdmin()->getList();

        self::assertTrue($list->has('definition'));
        self::assertTrue($list->has('event'));
        self::assertTrue($list->has('responsible'));
        self::assertTrue($list->has('startAt'));
        self::assertTrue($list->has('endAt'));
        self::assertTrue($list->has('disableBookings'));
        self::assertTrue($list->has(ListMapper::NAME_ACTIONS));
    }

    public function testListFieldsExcludeEventForChild(): void
    {
        $list = $this->childAdmin()->getList();

        self::assertFalse($list->has('event'));
        self::assertTrue($list->has('definition'));
    }

    public function testFormFieldsIncludeEventForStandalone(): void
    {
        $nakki = $this->createNakki();
        $admin = $this->standaloneAdmin();
        $admin->setSubject($nakki);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->has('event'));
        self::assertTrue($form->has('definition'));
        self::assertTrue($form->has('startAt'));
        self::assertTrue($form->has('endAt'));
    }

    public function testFormFieldsExcludeEventForChild(): void
    {
        $nakki = $this->createNakki();
        $admin = $this->childAdmin();
        $admin->setSubject($nakki);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse($form->has('event'));
        self::assertTrue($form->has('definition'));
    }

    public function testShowFieldsIncludeEvent(): void
    {
        $show = $this->standaloneAdmin()->getShow();

        self::assertTrue($show->has('definition'));
        self::assertTrue($show->has('event'));
        self::assertTrue($show->has('responsible'));
        self::assertTrue($show->has('startAt'));
        self::assertTrue($show->has('endAt'));
    }

    public function testRoutesIncludeClone(): void
    {
        $routes = $this->standaloneAdmin()->getRoutes();

        self::assertTrue($routes->has('clone'));
    }

    public function testPostPersistCreatesBookingsForInterval(): void
    {
        $start = new \DateTimeImmutable('2030-01-01 10:00:00');
        $end = new \DateTimeImmutable('2030-01-01 14:00:00');
        $nakki = $this->createManagedNakki(
            $start,
            $end,
            new \DateInterval('PT2H'),
        );

        $this->standaloneAdmin()->postPersist($nakki);

        $count = $this->em()->getRepository(NakkiBooking::class)->count([
            'nakki' => $nakki,
        ]);

        self::assertSame(2, $count);
    }

    public function testPostUpdateWarnsWhenBookingHasMember(): void
    {
        $nakki = $this->createManagedNakki();
        $memberProxy = MemberFactory::new()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        if (null !== $member->getId()) {
            $member = $this->em()->getRepository(Member::class)->find($member->getId()) ?? $member;
        }
        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setNakkikone($nakki->getNakkikone());
        $booking->setStartAt($nakki->getStartAt());
        $booking->setEndAt($nakki->getStartAt()->modify('+1 hour'));
        $booking->setMember($member);
        $this->em()->persist($booking);
        $this->em()->flush();
        $nakki->addNakkiBooking($booking);
        $this->em()->refresh($nakki);

        $requestStack = static::getContainer()->get(RequestStack::class);
        $request = $requestStack->getCurrentRequest();
        if (null === $request) {
            $request = Request::create('/admin/nakki');
            $requestStack->push($request);
        }

        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->start();

        $this->standaloneAdmin()->postUpdate($nakki);

        $warnings = $session->getFlashBag()->peek('warning');
        self::assertContains(
            'One or more Nakki has been reserved by a member. Edit Nakki bookings manually. Only details edited.',
            $warnings,
        );

        $count = $this->em()->getRepository(NakkiBooking::class)->count([
            'nakki' => $nakki,
        ]);
        self::assertSame(1, $count);
    }

    public function testPostUpdateRebuildsBookingsWithoutMembers(): void
    {
        $start = new \DateTimeImmutable('2030-02-02 10:00:00');
        $end = new \DateTimeImmutable('2030-02-02 16:00:00');
        $nakki = $this->createManagedNakki(
            $start,
            $end,
            new \DateInterval('PT3H'),
        );

        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setNakkikone($nakki->getNakkikone());
        $booking->setStartAt($start);
        $booking->setEndAt($start->modify('+3 hours'));
        $this->em()->persist($booking);
        $this->em()->flush();
        $nakki->addNakkiBooking($booking);
        $this->em()->refresh($nakki);
        $bookingId = $booking->getId();
        $this->standaloneAdmin()->postUpdate($nakki);

        $count = $this->em()->getRepository(NakkiBooking::class)->count([
            'nakki' => $nakki,
        ]);
        self::assertSame(2, $count);
        self::assertNull($this->em()->getRepository(NakkiBooking::class)->find($bookingId));
    }

    public function testPostDeleteRemovesBookings(): void
    {
        $nakki = $this->createManagedNakki();
        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setNakkikone($nakki->getNakkikone());
        $booking->setStartAt($nakki->getStartAt());
        $booking->setEndAt($nakki->getStartAt()->modify('+1 hour'));
        $this->em()->persist($booking);
        $this->em()->flush();
        $this->standaloneAdmin()->postDelete($nakki);

        $count = $this->em()->getRepository(NakkiBooking::class)->count([
            'nakki' => $nakki,
        ]);
        self::assertSame(0, $count);
    }

    public function testCreateBookingReturnsEarlyOnNull(): void
    {
        $admin = $this->standaloneAdmin();
        $method = new \ReflectionMethod(NakkiAdmin::class, 'createBooking');
        $method->setAccessible(true);

        $method->invoke($admin, null, 0);

        self::assertTrue(true);
    }

    public function testValidateRequiresDefinition(): void
    {
        $nakki = new Nakki();
        $definition = $this->createManagedDefinition();
        $nakki->setDefinition($definition);
        $validator = static::getContainer()->get(ValidatorInterface::class);
        $translator = static::getContainer()->get(TranslatorInterface::class);
        $context = new ExecutionContext($validator, $nakki, $translator);
        $errorElement = new \Sonata\Form\Validator\ErrorElement($nakki, $context, null);

        $this->standaloneAdmin()->validate($errorElement, $nakki);

        self::assertSame(0, $context->getViolations()->count());
    }

    private function createNakki(): Nakki
    {
        return $this->createManagedNakki();
    }

    private function createManagedNakki(
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        ?\DateInterval $interval = null,
    ): Nakki {
        $start ??= new \DateTimeImmutable('2030-01-01 10:00:00');
        $end ??= $start->modify('+1 hour');
        $interval ??= new \DateInterval('PT1H');

        $eventProxy = EventFactory::new()->published()->create();
        $event = $eventProxy instanceof Proxy ? $eventProxy->_real() : $eventProxy;
        if (null !== $event->getId()) {
            $event = $this->em()->getRepository(Event::class)->find($event->getId()) ?? $event;
        }

        $nakkikone = new Nakkikone($event);
        $event->setNakkikone($nakkikone);

        $definition = $this->createManagedDefinition();

        $nakki = new Nakki();
        $nakki->setDefinition($definition);
        $nakki->setNakkikone($nakkikone);
        $nakki->setStartAt($start);
        $nakki->setEndAt($end);
        $nakki->setNakkiInterval($interval);

        $this->em()->persist($event);
        $this->em()->persist($nakkikone);
        $this->em()->persist($nakki);
        $this->em()->flush();

        return $nakki;
    }

    private function createManagedDefinition(): NakkiDefinition
    {
        $definition = new NakkiDefinition();
        $definition->setNameFi('Test nakki');
        $definition->setNameEn('Test nakki');
        $definition->setDescriptionFi('Test description');
        $definition->setDescriptionEn('Test description');

        $this->em()->persist($definition);
        $this->em()->flush();

        return $definition;
    }

    private function standaloneAdmin(): NakkiAdmin
    {
        $admin = clone $this->nakkiAdmin();
        $this->clearParent($admin);
        $this->resetAdminCaches($admin);

        return $admin;
    }

    private function childAdmin(): NakkiAdmin
    {
        $admin = clone $this->nakkiAdmin();
        $this->clearParent($admin);
        $this->setChildContext($admin);
        $this->resetAdminCaches($admin);

        return $admin;
    }

    private function nakkiAdmin(): NakkiAdmin
    {
        $admin = static::getContainer()->get('entropy.admin.nakki');
        \assert($admin instanceof NakkiAdmin);

        return $admin;
    }

    private function setChildContext(AbstractAdmin $admin): void
    {
        $parent = static::getContainer()->get('entropy.admin.event');
        \assert($parent instanceof AbstractAdmin);
        $admin->setParent($parent, 'event');
    }

    private function clearParent(AbstractAdmin $admin): void
    {
        $parentRef = new \ReflectionProperty(AbstractAdmin::class, 'parent');
        $parentRef->setAccessible(true);
        $parentRef->setValue($admin, null);

        $mappingRef = new \ReflectionProperty(AbstractAdmin::class, 'parentAssociationMapping');
        $mappingRef->setAccessible(true);
        $mappingRef->setValue($admin, []);
    }

    private function resetAdminCaches(AbstractAdmin $admin): void
    {
        $loadedRef = new \ReflectionProperty(AbstractAdmin::class, 'loaded');
        $loadedRef->setAccessible(true);
        $loadedRef->setValue($admin, [
            'routes' => false,
            'tab_menu' => false,
            'show' => false,
            'list' => false,
            'form' => false,
            'datagrid' => false,
        ]);

        foreach (['list', 'show', 'form', 'datagrid', 'routes'] as $property) {
            $ref = new \ReflectionProperty(AbstractAdmin::class, $property);
            $ref->setAccessible(true);
            $ref->setValue($admin, null);
        }
    }
}
