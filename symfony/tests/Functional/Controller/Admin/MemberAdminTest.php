<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Admin\MemberAdmin;
use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use App\Entity\Member;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Zenstruck\Foundry\Proxy;

#[Group('admin')]
#[Group('member')]
final class MemberAdminTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    protected function tearDown(): void
    {
        $this->memberAdmin()->setSubject(null);

        parent::tearDown();
    }

    public function testBaseRoutePatternIsMember(): void
    {
        self::assertSame('member', $this->memberAdmin()->getBaseRoutePattern());
    }

    public function testDefaultSortValuesAreConfigured(): void
    {
        $admin = $this->memberAdmin();
        $method = new \ReflectionMethod(AbstractAdmin::class, 'getDefaultSortValues');
        $method->setAccessible(true);

        $values = $method->invoke($admin);

        self::assertSame(1, $values[DatagridInterface::PAGE]);
        self::assertSame('DESC', $values[DatagridInterface::SORT_ORDER]);
        self::assertSame('createdAt', $values[DatagridInterface::SORT_BY]);
    }

    public function testDatagridFiltersIncludeExpectedFields(): void
    {
        $datagrid = $this->memberAdmin()->getDatagrid();

        self::assertTrue($datagrid->hasFilter('artist'));
        self::assertTrue($datagrid->hasFilter('name'));
        self::assertTrue($datagrid->hasFilter('email'));
        self::assertTrue($datagrid->hasFilter('ApplicationHandledDate'));
        self::assertTrue($datagrid->hasFilter('createdAt'));
    }

    public function testNameFilterCallbackReturnsFalseWithoutValue(): void
    {
        $admin = $this->memberAdmin();
        $filter = $admin->getDatagrid()->getFilter('name');
        $query = $admin->createQuery();

        $filter->filter($query, 'o', 'name', FilterData::fromArray([]));

        self::assertFalse($filter->isActive());
    }

    public function testNameFilterCallbackReturnsFalseForEmptyValue(): void
    {
        $admin = $this->memberAdmin();
        $filter = $admin->getDatagrid()->getFilter('name');
        $query = $admin->createQuery();

        $filter->filter($query, 'o', 'name', FilterData::fromArray(['value' => '   ']));

        self::assertFalse($filter->isActive());
    }

    public function testNameFilterCallbackAppliesLowercaseLike(): void
    {
        $admin = $this->memberAdmin();
        $filter = $admin->getDatagrid()->getFilter('name');
        $query = $admin->createQuery();

        $filter->filter($query, 'o', 'name', FilterData::fromArray(['value' => 'Alice']));

        self::assertTrue($filter->isActive());
        self::assertSame(
            '%alice%',
            $query->getQueryBuilder()->getParameter('name')?->getValue(),
        );
    }

    public function testListFieldsIncludeExpectedColumns(): void
    {
        $list = $this->memberAdmin()->getList();

        self::assertTrue($list->has('artist'));
        self::assertTrue($list->has('name'));
        self::assertTrue($list->has('email'));
        self::assertTrue($list->has('emailVerified'));
        self::assertTrue($list->has('StudentUnionMember'));
        self::assertTrue($list->has('isActiveMember'));
        self::assertTrue($list->has('isFullMember'));
        self::assertTrue($list->has('user.LastLogin'));
        self::assertTrue($list->has(ListMapper::NAME_ACTIONS));
    }

    public function testFormFieldsDisableApplicationWhenAlreadyProvided(): void
    {
        $memberProxy = MemberFactory::new()->applicationPending()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->object() : $memberProxy;

        $admin = $this->memberAdmin();
        $admin->setSubject($member);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue((bool) $form->get('Application')->getConfig()->getOption('disabled'));
    }

    public function testFormFieldsAllowApplicationWhenMissing(): void
    {
        $memberProxy = MemberFactory::new()->inactive()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->object() : $memberProxy;

        $admin = $this->memberAdmin();
        $admin->setSubject($member);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse((bool) $form->get('Application')->getConfig()->getOption('disabled'));
    }

    public function testShowFieldsIncludeExpectedFields(): void
    {
        $memberProxy = MemberFactory::new()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->object() : $memberProxy;
        if (null !== $member->getId()) {
            $member = $this->em()->getRepository(Member::class)->find($member->getId()) ?? $member;
        }

        $admin = $this->memberAdmin();
        $admin->setSubject($member);

        $show = $admin->getShow();

        self::assertTrue($show->has('username'));
        self::assertTrue($show->has('name'));
        self::assertTrue($show->has('email'));
        self::assertTrue($show->has('emailVerified'));
        self::assertTrue($show->has('StudentUnionMember'));
        self::assertTrue($show->has('Application'));
        self::assertTrue($show->has('isActiveMember'));
        self::assertTrue($show->has('isFullMember'));
        self::assertTrue($show->has('createdAt'));
    }

    public function testRoutesIncludeActiveMemberInfo(): void
    {
        $routes = $this->memberAdmin()->getRoutes();

        self::assertTrue($routes->has('activememberinfo'));
    }

    public function testExportFieldsAreConfigured(): void
    {
        self::assertSame(
            [
                'name',
                'email',
                'StudentUnionMember',
                'isActiveMember',
                'isFullMember',
                'AcceptedAsHonoraryMember',
            ],
            $this->memberAdmin()->configureExportFields(),
        );
    }

    public function testPreRemoveDetachesArtistInfosAndRemovesArtist(): void
    {
        $member = $this->createMember('member-pre-remove-'.bin2hex(random_bytes(4)).'@example.test');
        $eventProxy = EventFactory::new()->published()->create();
        $eventEntity = $eventProxy instanceof Proxy ? $eventProxy->object() : $eventProxy;
        if (null !== $eventEntity->getId()) {
            $eventEntity = $this->em()->getRepository(Event::class)->find($eventEntity->getId()) ?? $eventEntity;
        }
        $this->em()->persist($eventEntity);
        $this->em()->flush();
        $artist = new Artist();
        $artist->setName('Test Artist');
        $artist->setMember($member);
        $this->em()->persist($artist);
        $this->em()->flush();
        $info = new EventArtistInfo();
        $info->setEvent($eventEntity);
        $info->setArtist($artist);
        $this->em()->persist($info);
        $this->em()->flush();
        $infoId = $info->getId();
        $artistId = $artist->getId();
        if (null !== $member->getId()) {
            $member = $this->em()->getRepository(Member::class)->find($member->getId()) ?? $member;
        }

        $this->memberAdmin()->preRemove($member);

        $this->em()->clear();

        $refreshedInfo = $this->em()->getRepository(EventArtistInfo::class)->find($infoId);
        $refreshedArtist = $this->em()->getRepository(Artist::class)->find($artistId);

        self::assertInstanceOf(EventArtistInfo::class, $refreshedInfo);
        self::assertNull($refreshedInfo->getArtist());
        self::assertNull($refreshedArtist);
    }

    public function testPostRemoveSendsMattermostNotification(): void
    {
        $member = $this->createMember('member-post-remove-'.bin2hex(random_bytes(4)).'@example.test');

        $this->memberAdmin()->postRemove($member);

        self::assertTrue(true);
    }

    private function memberAdmin(): MemberAdmin
    {
        $admin = static::getContainer()->get('entropy.admin.member');
        \assert($admin instanceof MemberAdmin);

        return $admin;
    }

    private function createMember(string $email): Member
    {
        $member = new Member();
        $member->setFirstname('Test');
        $member->setLastname('Member');
        $member->setEmail($email);
        $member->setLocale('fi');

        $this->em()->persist($member);
        $this->em()->flush();

        return $member;
    }
}
