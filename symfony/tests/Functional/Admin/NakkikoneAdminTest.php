<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\EventAdmin;
use App\Admin\NakkikoneAdmin;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Sonata's Instantiator bypasses the constructor for entities whose constructor
 * has required arguments (Nakkikone requires an Event). This means a freshly
 * created "new" Nakkikone never runs through __construct(), leaving its typed
 * Collection properties (nakkis, bookings, responsibleAdmins) uninitialized.
 *
 * @see https://github.com (regression: PropertyAccess UninitializedPropertyException on
 *      App\Entity\Nakkikone::$responsibleAdmins when opening /admin/app/nakkikone/create)
 */
#[Group('admin')]
#[Group('nakkikone')]
final class NakkikoneAdminTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testFormBuildsForFreshlyInstantiatedNakkikoneWithoutUninitializedPropertyError(): void
    {
        $admin = $this->admin();

        // Mirrors AbstractAdmin::getNewInstance(): a "new" Nakkikone built via reflection,
        // without invoking the constructor.
        $obj = $admin->getNewInstance();
        $admin->setSubject($obj);

        // Building the form reads every mapped field (including responsibleAdmins) via
        // PropertyAccessor; this must not throw UninitializedPropertyException.
        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->has('responsibleAdmins'));
        self::assertSame(
            [],
            iterator_to_array($obj->getResponsibleAdmins()),
            'A freshly instantiated Nakkikone should expose an empty (not uninitialized) admins collection.',
        );
    }

    /**
     * Reproduces the actual production crash: saving (not merely viewing) the edit form
     * of an old Event whose Nakkikone was never created (e.g. it predates the feature, or
     * was never re-saved since). Symfony Form's PropertyPathAccessor gracefully swallows
     * UninitializedPropertyException on *read* (see the test above), but on *write* —
     * PropertyAccessor::writeCollection() reading the "previous value" to diff against
     * submitted responsibleAdmins entries via the adder/remover pattern — that graceful
     * handling does not apply, and the exception propagated uncaught in production.
     */
    public function testEventFormSubmitDoesNotCrashWhenEditingEventWithoutNakkikone(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-without-nakkikone-'.uniqid('', true),
            'nakkikone' => null,
        ]);
        self::assertNull($event->getNakkikone());

        $admin = static::getContainer()->get('entropy.admin.event');
        \assert($admin instanceof EventAdmin);
        $admin->setSubject($event);

        $form = $admin->getFormBuilder()->getForm();

        // Minimal submission: only the nested nakkikone.responsibleAdmins field matters here.
        // This exercises the exact write path (add*/remove* diffing against the current,
        // previously-uninitialized collection) that crashed in production.
        $form->submit([
            'nakkikone' => [
                'responsibleAdmins' => [],
            ],
        ], false);

        self::assertTrue($form->isSubmitted());
        self::assertSame(
            [],
            iterator_to_array($form->get('nakkikone')->getData()->getResponsibleAdmins()),
        );
    }

    private function admin(): NakkikoneAdmin
    {
        $admin = static::getContainer()->get('entropy.admin.nakkikone');
        \assert($admin instanceof NakkikoneAdmin);

        return $admin;
    }
}
