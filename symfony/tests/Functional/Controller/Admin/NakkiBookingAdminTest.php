<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Admin\NakkiBookingAdmin;
use App\Entity\NakkiBooking;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiBookingFactory;
use App\Factory\NakkikoneFactory;
use App\Factory\TicketFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Zenstruck\Foundry\Persistence\Proxy;

#[Group('admin')]
#[Group('nakki')]
final class NakkiBookingAdminTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testDatagridFiltersIncludeEventForStandalone(): void
    {
        $admin = $this->standaloneAdmin();
        $datagrid = $admin->getDatagrid();

        self::assertTrue($datagrid->hasFilter('nakki'));
        self::assertTrue($datagrid->hasFilter('event'));
        self::assertTrue($datagrid->hasFilter('display_only_unique_members'));
        self::assertTrue($datagrid->hasFilter('member'));
        self::assertTrue($datagrid->hasFilter('memberNotAssigned'));
        self::assertTrue($datagrid->hasFilter('startAt'));
        self::assertTrue($datagrid->hasFilter('startAtRange'));
        self::assertTrue($datagrid->hasFilter('endAt'));
    }

    public function testDatagridFiltersIncludeParentEventForChild(): void
    {
        $admin = $this->childAdmin();
        self::assertTrue($admin->isChild());
        $datagrid = $admin->getDatagrid();

        self::assertTrue($datagrid->hasFilter('event'));
        self::assertTrue($datagrid->hasFilter('nakki'));
    }

    public function testUniqueMembersFilterInactiveWhenNoValue(): void
    {
        $admin = $this->standaloneAdmin();
        $filter = $admin->getDatagrid()->getFilter('display_only_unique_members');
        $query = $admin->createQuery();

        $filter->filter($query, 'o', 'display_only_unique_members', FilterData::fromArray([]));

        self::assertFalse($filter->isActive());
    }

    public function testUniqueMembersFilterGroupsByMemberWhenEnabled(): void
    {
        $admin = $this->standaloneAdmin();
        $filter = $admin->getDatagrid()->getFilter('display_only_unique_members');
        $query = $admin->createQuery();

        $filter->filter($query, 'o', 'display_only_unique_members', FilterData::fromArray(['value' => true]));

        self::assertTrue($filter->isActive());
        $groupBy = $query->getQueryBuilder()->getDQLPart('groupBy');
        $groupByString = implode(',', array_map('strval', (array) $groupBy));
        self::assertStringContainsString('o.member', $groupByString);
    }

    public function testListFieldsIncludeEventForStandalone(): void
    {
        $list = $this->standaloneAdmin()->getList();

        self::assertTrue($list->has('nakki'));
        self::assertTrue($list->has('event'));
        self::assertTrue($list->has('member'));
        self::assertTrue($list->has('memberHasEventTicket'));
        self::assertTrue($list->has('startAt'));
        self::assertTrue($list->has('endAt'));
        self::assertTrue($list->has(ListMapper::NAME_ACTIONS));
    }

    public function testListFieldsExcludeEventForChild(): void
    {
        $list = $this->childAdmin()->getList();

        self::assertFalse($list->has('event'));
        self::assertTrue($list->has('nakki'));
    }

    public function testFormFieldsIncludeEventForStandalone(): void
    {
        $booking = $this->createBooking();
        $admin = $this->standaloneAdmin();
        $admin->setSubject($booking);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->has('event'));
        self::assertTrue($form->has('nakki'));
        self::assertTrue($form->has('member'));
        self::assertTrue($form->has('startAt'));
        self::assertTrue($form->has('endAt'));
    }

    public function testFormFieldsExcludeEventForChild(): void
    {
        $booking = $this->createBooking();
        $admin = $this->childAdmin();
        $admin->setSubject($booking);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse($form->has('event'));
        self::assertTrue($form->has('nakki'));
    }

    public function testShowFieldsIncludeExpectedFields(): void
    {
        $show = $this->standaloneAdmin()->getShow();

        self::assertTrue($show->has('nakki'));
        self::assertTrue($show->has('event'));
        self::assertTrue($show->has('member'));
        self::assertTrue($show->has('startAt'));
        self::assertTrue($show->has('endAt'));
    }

    public function testMemberEmailAndHasEventTicket(): void
    {
        $member = MemberFactory::new()->create([
            'email' => 'nakki.member@example.test',
        ]);
        $event = EventFactory::new()->published()->create();
        $nakkikone = NakkikoneFactory::new()->create([
            'event' => $event,
        ]);
        $nakki = \App\Factory\NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
        ]);

        $booking = NakkiBookingFactory::new()->booked()->create([
            'member' => $member,
            'nakkikone' => $nakkikone,
            'nakki' => $nakki,
        ]);
        $bookingEntity = $booking instanceof Proxy ? $booking->_real() : $booking;

        $nakkikone->setRequiredForTicketReservation(true);
        $this->em()->flush();
        self::assertSame(
            (string) $event.': '.$bookingEntity->getNakki(),
            (string) $bookingEntity,
        );

        $nakkikone->setRequiredForTicketReservation(false);
        $bookingEntity->setStartAt(new \DateTimeImmutable('2030-01-01 09:15:00'));
        $this->em()->flush();
        self::assertSame(
            (string) $event.': '.$bookingEntity->getNakki().' at 09:15',
            (string) $bookingEntity,
        );

        self::assertSame('nakki.member@example.test', $bookingEntity->getMemberEmail());
        self::assertFalse($bookingEntity->memberHasEventTicket());

        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->paid()
            ->create();
        $ticketEntity = $ticket instanceof Proxy ? $ticket->_real() : $ticket;
        $event->addTicket($ticketEntity);
        $this->em()->flush();

        self::assertTrue($bookingEntity->memberHasEventTicket());

        $bookingEntity->setMember(null);
        self::assertNull($bookingEntity->getMemberEmail());
    }

    private function createBooking(): NakkiBooking
    {
        $bookingProxy = NakkiBookingFactory::new()->create();

        return $bookingProxy instanceof Proxy ? $bookingProxy->_real() : $bookingProxy;
    }

    private function standaloneAdmin(): NakkiBookingAdmin
    {
        $admin = clone $this->nakkiBookingAdmin();
        $this->clearParent($admin);
        $this->resetAdminCaches($admin);

        return $admin;
    }

    private function childAdmin(): NakkiBookingAdmin
    {
        $admin = clone $this->nakkiBookingAdmin();
        $this->clearParent($admin);
        $this->setChildContext($admin);
        $this->resetAdminCaches($admin);

        return $admin;
    }

    private function nakkiBookingAdmin(): NakkiBookingAdmin
    {
        $admin = static::getContainer()->get('entropy.admin.nakki_booking');
        \assert($admin instanceof NakkiBookingAdmin);

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
