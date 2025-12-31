<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Admin\EmailAdmin;
use App\Entity\Email;
use App\Enum\EmailPurpose;
use App\Factory\EmailFactory;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Zenstruck\Foundry\Proxy;

#[Group('admin')]
#[Group('email')]
final class EmailAdminTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    protected function tearDown(): void
    {
        $admin = $this->emailAdmin();
        $admin->setSubject(null);
        $this->clearParent($admin);

        parent::tearDown();
    }

    public function testConfigureRoutesRegistersPreviewAndSendActions(): void
    {
        $routes = $this->emailAdmin()->getRoutes();

        self::assertFalse($routes->has('show'));
        self::assertTrue($routes->has('preview'));
        self::assertTrue($routes->has('send'));
        self::assertTrue($routes->has('send_progress'));
    }

    public function testBaseRoutePatternIsEmail(): void
    {
        self::assertSame('email', $this->emailAdmin()->getBaseRoutePattern());
    }

    public function testDatagridFiltersIncludeExpectedFields(): void
    {
        $datagrid = $this->emailAdmin()->getDatagrid();

        self::assertTrue($datagrid->hasFilter('purpose'));
        self::assertTrue($datagrid->hasFilter('event'));
        self::assertTrue($datagrid->hasFilter('subject'));
        self::assertTrue($datagrid->hasFilter('body'));
        self::assertTrue($datagrid->hasFilter('sentAt'));
        self::assertTrue($datagrid->hasFilter('sentBy'));
    }

    public function testListFieldsForStandaloneIncludeEventAndPurpose(): void
    {
        $list = $this->emailAdmin()->getList();

        self::assertTrue($list->has('event'));
        self::assertTrue($list->has('purpose'));
        self::assertTrue($list->has('subject'));
        self::assertTrue($list->has('updatedAt'));
        self::assertTrue($list->has('sentAt'));
        self::assertTrue($list->has('sentBy'));
    }

    public function testListFieldsForChildExcludeEvent(): void
    {
        $admin = $this->emailAdmin();
        $this->setChildContext($admin);

        $list = $admin->getList();

        self::assertFalse($list->has('event'));
        self::assertTrue($list->has('purpose'));
    }

    public function testConfigureShowFieldsIncludesExpectedFields(): void
    {
        $emailProxy = EmailFactory::new()->create();
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $admin->setSubject($email);

        $show = $admin->getShow();

        self::assertTrue($show->has('purpose'));
        self::assertTrue($show->has('subject'));
        self::assertTrue($show->has('body'));
        self::assertTrue($show->has('addLoginLinksToFooter'));
        self::assertTrue($show->has('createdAt'));
        self::assertTrue($show->has('updatedAt'));
    }

    public function testStandaloneFormFieldsCreateOmitsEventAndRecipientGroups(): void
    {
        $email = new Email();

        $admin = $this->emailAdmin();
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse($form->has('event'));
        self::assertFalse($form->has('recipientGroups'));
        self::assertFalse($form->has('subject'));
        self::assertFalse($form->has('body'));
        self::assertFalse($form->has('addLoginLinksToFooter'));
    }

    public function testCreatePurposeFilterAllowsCurrentStandalonePurpose(): void
    {
        $email = new Email();
        $email->setPurpose(EmailPurpose::TIEDOTUS);

        $admin = $this->emailAdmin();
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();
        $choiceFilter = $form->get('purpose')->getConfig()->getOption('choice_filter');

        self::assertIsCallable($choiceFilter);
        self::assertTrue($choiceFilter(EmailPurpose::TIEDOTUS));
    }

    public function testStandaloneFormFieldsEditShowsReadonlyEventAndRecipientGroups(): void
    {
        $event = EventFactory::new()->published()->create();
        $emailProxy = EmailFactory::new()->forEvent($event)->create();
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->has('event'));
        self::assertTrue((bool) $form->get('event')->getConfig()->getOption('disabled'));
        self::assertFalse($form->has('recipientGroups'));
    }

    public function testStandalonePurposeFilterExcludesSingletons(): void
    {
        EmailFactory::new()->create(['purpose' => EmailPurpose::MEMBER_WELCOME]);
        $currentProxy = EmailFactory::new()->create(['purpose' => EmailPurpose::TIEDOTUS]);
        $current = $currentProxy instanceof Proxy ? $currentProxy->object() : $currentProxy;

        $admin = $this->emailAdmin();
        $admin->setSubject($current);

        $form = $admin->getFormBuilder()->getForm();
        $choiceFilter = $form->get('purpose')->getConfig()->getOption('choice_filter');

        self::assertIsCallable($choiceFilter);
        self::assertFalse($choiceFilter(EmailPurpose::MEMBER_WELCOME));
        self::assertTrue($choiceFilter(EmailPurpose::TIEDOTUS));
    }

    public function testCommonFormFieldsDisableSubjectForTicketQr(): void
    {
        $emailProxy = EmailFactory::new()->ticketQr()->create([
            'subject' => 'Placeholder',
        ]);
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();
        $subjectConfig = $form->get('subject')->getConfig();

        self::assertTrue((bool) $subjectConfig->getOption('disabled'));
        self::assertSame(
            '[event name] Ticket #1 / Lippusi #1',
            $subjectConfig->getOption('data'),
        );
    }

    public function testChildAdminHidesRecipientGroupsForTicketQr(): void
    {
        $event = EventFactory::new()->published()->create();
        $emailProxy = EmailFactory::new()->ticketQr()->forEvent($event)->create();
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $this->setChildContext($admin);
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse($form->has('recipientGroups'));
    }

    public function testChildAdminIncludesRecipientGroupsForEventPurpose(): void
    {
        $event = EventFactory::new()->published()->create();
        $emailProxy = EmailFactory::new()->ticket()->forEvent($event)->create();
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $this->setChildContext($admin);
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->has('recipientGroups'));
    }

    public function testChildCreatePurposeFilterAllowsCurrentPurpose(): void
    {
        $email = new Email();
        $email->setPurpose(EmailPurpose::TICKET);

        $admin = $this->emailAdmin();
        $this->setChildContext($admin);
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();
        $choiceFilter = $form->get('purpose')->getConfig()->getOption('choice_filter');

        self::assertIsCallable($choiceFilter);
        self::assertTrue($choiceFilter(EmailPurpose::TICKET));
    }

    public function testChildCreateOmitsNonPurposeFields(): void
    {
        $email = new Email();

        $admin = $this->emailAdmin();
        $this->setChildContext($admin);
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse($form->has('recipientGroups'));
        self::assertFalse($form->has('replyTo'));
        self::assertFalse($form->has('subject'));
        self::assertFalse($form->has('body'));
        self::assertFalse($form->has('addLoginLinksToFooter'));
    }

    public function testChildRecipientGroupsChoiceFilterHandlesNullAndExisting(): void
    {
        $event = EventFactory::new()->published()->create();
        $emailProxy = EmailFactory::new()->ticket()->forEvent($event)->create([
            'recipientGroups' => [EmailPurpose::AKTIIVIT],
        ]);
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $this->setChildContext($admin);
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();
        $choiceFilter = $form->get('recipientGroups')->getConfig()->getOption('choice_filter');

        self::assertIsCallable($choiceFilter);
        self::assertFalse($choiceFilter(null));
        self::assertTrue($choiceFilter(EmailPurpose::AKTIIVIT));
    }

    public function testExistingSingletonPurposesReturnsEmptyWithoutSubject(): void
    {
        $admin = $this->emailAdmin();
        $subjectRef = new \ReflectionProperty(AbstractAdmin::class, 'subject');
        $subjectRef->setAccessible(true);
        $subjectRef->setValue($admin, null);

        $method = new \ReflectionMethod(EmailAdmin::class, 'getExistingSingletonPurposes');
        $method->setAccessible(true);

        self::assertSame([], $method->invoke($admin));
    }

    public function testChildAdminIncludesReplyToField(): void
    {
        $event = EventFactory::new()->published()->create();
        $emailProxy = EmailFactory::new()->ticket()->forEvent($event)->create();
        $email = $emailProxy instanceof Proxy ? $emailProxy->object() : $emailProxy;

        $admin = $this->emailAdmin();
        $this->setChildContext($admin);
        $admin->setSubject($email);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->has('replyTo'));
    }

    private function emailAdmin(): EmailAdmin
    {
        $admin = static::getContainer()->get('admin.email');
        \assert($admin instanceof EmailAdmin);

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
}
