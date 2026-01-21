<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Event;
use App\Entity\Location;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiBookingFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkikoneFactory;
use App\Factory\RSVPFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\String\Slugger\SluggerInterface;
use Zenstruck\Foundry\Proxy;

/**
 * Functional tests for Event admin controller + custom routes.
 *
 * Coverage goals:
 * - Ensure the "Artist Display" embeddable form is wired correctly in EventAdmin
 *   and that submitted checkbox values are persisted.
 * - Ensure custom CRUD controller actions (nakki list + RSVP list) render successfully
 *   for admins and pass expected variables to their templates.
 */
#[Group('admin')]
#[Group('event')]
final class EventAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testEditActionCanPersistArtistDisplayConfigurationFlags(): void
    {
        // Create an event we can edit in admin. Ensure unique slug/url to avoid collisions.
        $event = EventFactory::new()->published()->create([
            'url' => 'event-artist-display-config-'.uniqid('', true),
            'name' => 'Event Artist Display Config EN',
            'nimi' => 'Event Artist Display Config FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Load the edit form to obtain the correct form name and any CSRF tokens.
        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        // Sonata edit pages may render multiple forms (filters, delete, inline forms, etc.).
        // Select the *main* edit form by looking for the embedded field root rather than picking the first form.
        $formNode = null;

        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            // Look for any input whose name contains "[artistDisplayConfiguration]" within this form.
            // Note: CSS attribute selectors need quotes when the value contains "[" / "]".
            if ($candidate->filter('input[name*="[artistDisplayConfiguration]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }

        self::assertNotNull(
            $formNode,
            'Expected to find the Sonata edit form containing artistDisplayConfiguration fields (Artist Display tab may be hidden/collapsed).'
        );

        // We cannot reliably use BrowserKit's Form field registry here, because Sonata uses a generated
        // root name (e.g. "s6943fb6a30aea[...]") and the registry may not expose the compound
        // "artistDisplayConfiguration" field as a reachable node.
        //
        // Instead, detect the Sonata root token from the real input names and submit a raw request payload
        // (merging on top of the form's existing php values so we keep CSRF + hidden fields).
        $form = $formNode->form();
        $values = $form->getPhpValues();

        $html = $crawler->html() ?? '';
        $root = null;

        // Root form name is the token before the first "[" in input names like:
        //   name="s6943fb6a30aea[artistDisplayConfiguration][djTimetable][djTimetableIncludePageLinks]"
        if (1 === preg_match('/name="([^"]+)\[artistDisplayConfiguration\]\[djTimetable\]\[djTimetableIncludePageLinks\]"/', $html, $m)) {
            $root = $m[1];
        } elseif (1 === preg_match('/name="([^"]+)\[artistDisplayConfiguration\]\[vjTimetable\]\[vjTimetableShowGenre\]"/', $html, $m)) {
            $root = $m[1];
        } elseif (1 === preg_match('/name="([^"]+)\[artistDisplayConfiguration\]\[artBio\]\[artBioShowTime\]"/', $html, $m)) {
            $root = $m[1];
        }

        self::assertNotNull(
            $root,
            'Could not detect Sonata form root name for artistDisplayConfiguration fields'
        );

        // The HTML you supplied shows the nested payload shape Sonata renders:
        //   sXXXX[artistDisplayConfiguration][djTimetable][djTimetableIncludePageLinks]
        // so we update those exact keys.
        $values[$root]['artistDisplayConfiguration']['djTimetable']['djTimetableIncludePageLinks'] = '1'; // false -> true

        // Checkbox semantics: unchecked checkboxes typically submit no key at all.
        // Setting an explicit "0" may be ignored depending on the form wiring, so remove the key to
        // simulate a real user unchecking it.
        unset($values[$root]['artistDisplayConfiguration']['vjTimetable']['vjTimetableShowGenre']);       // true -> false

        $values[$root]['artistDisplayConfiguration']['artBio']['artBioShowTime'] = '1';                  // false -> true

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // Accept both behaviors:
        // - some Sonata setups redirect after successful update
        // - others re-render the edit page with HTTP 200
        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302], 'Expected successful update response (200 or 302)');
        if (302 === $status) {
            $this->client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            // On 200 responses, surface any form errors to make failures actionable.
            $this->client->assertSelectorNotExists(
                '.has-error',
                'Form validation errors detected in response HTML'
            );
            $this->client->assertSelectorNotExists(
                '.sonata-ba-form-error',
                'Form errors detected in response HTML'
            );
        }

        // Reload the entity and assert persisted config reflects the changes.
        $em = $this->em();
        $em->clear();

        $reloaded = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($reloaded);

        $cfg = $reloaded->getArtistDisplayConfiguration();

        self::assertTrue(
            $cfg->shouldTimetableIncludePageLinks('DJ'),
            'Expected DJ timetable include_page_links to persist as true'
        );

        self::assertFalse(
            $cfg->shouldTimetableShowGenre('VJ'),
            'Expected VJ timetable include_genre to persist as false'
        );

        self::assertTrue(
            $cfg->shouldBioShowTime('ART'),
            'Expected ART bio show_time to persist as true'
        );
    }

    public function testNakkiListActionRendersAndListsBookedMembers(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-nakki-list-'.uniqid('', true),
            'name' => 'Event Nakki List EN',
            'nimi' => 'Event Nakki List FI',
        ]);
        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
        ]);

        // Create one booked booking to ensure emails + table render.
        $booking = NakkiBookingFactory::new()->booked()->create([
            'nakki' => NakkiFactory::new()->with(['nakkikone' => $nakkikone]),
            'nakkikone' => $nakkikone,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/nakkilist");
        $this->assertResponseIsSuccessful();

        // Structural checks on template output (avoid brittle HTML substring checks).
        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', (string) $event);

        // Should render a "Contacts" section if there are bookings.
        $this->client->assertSelectorExists('table.table');

        // Ensure at least one row for the booked member exists.
        $member = $booking->getMember();
        self::assertNotNull($member);
        $this->client->assertSelectorTextContains('table.table tbody', (string) $member->getEmail());
    }

    public function testRsvpActionRendersDoorListForEventRsvps(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-rsvp-list-'.uniqid('', true),
            'name' => 'Event RSVP List EN',
            'nimi' => 'Event RSVP List FI',
        ]);

        // Create RSVPs so the table renders and sorting by availableLastName is exercised.
        $rsvpEmail = 'rsvp-test-'.bin2hex(random_bytes(4)).'@example.com';
        $rsvp = RSVPFactory::new()->create([
            'event' => $event,
            'firstName' => 'Test',
            'lastName' => 'Person',
            'email' => $rsvpEmail,
        ]);
        $member = MemberFactory::new()->create([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);
        $memberRsvpEmail = 'member-rsvp-'.bin2hex(random_bytes(4)).'@example.com';
        $memberRsvp = RSVPFactory::new()->create([
            'event' => $event,
            'member' => $member,
            'firstName' => 'Ignored',
            'lastName' => 'Ignored',
            'email' => $memberRsvpEmail,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/rsvp");
        $this->assertResponseIsSuccessful();

        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', (string) $event);

        // "Door list" header exists.
        $this->client->assertSelectorExists('h3');

        // RSVP row should include the provided name (structural table assertion).
        $this->client->assertSelectorExists('table.table');
        $this->client->assertSelectorTextContains('table.table tbody', $rsvp->getLastName());
        $this->client->assertSelectorTextContains('table.table tbody', $rsvp->getFirstName());
        $this->client->assertSelectorTextContains('table.table tbody', 'Lovelace');

        self::assertNotNull($rsvp->getId());
        self::assertInstanceOf(\DateTimeImmutable::class, $rsvp->getCreatedAt());
        self::assertSame('Test Person', $rsvp->getName());
        self::assertSame('Person', $rsvp->getAvailableLastName());
        self::assertSame($rsvpEmail, $rsvp->getAvailableEmail());
        self::assertSame('ID: '.$rsvp->getId(), (string) $rsvp);

        self::assertSame('Lovelace', $memberRsvp->getAvailableLastName());
        self::assertNotNull($member->getEmail());
        self::assertSame($member->getEmail(), $memberRsvp->getAvailableEmail());
    }

    /**
     * Test that external URL events can be updated without "extra fields" validation error.
     *
     * When externalUrl=true, the form excludes backgroundEffectConfig and related fields.
     * Previously, the PRE_SUBMIT handler would still manipulate backgroundEffectConfig,
     * causing a "This form should not contain extra fields" validation error.
     */
    public function testExternalUrlEventCanBeUpdatedWithoutExtraFieldsError(): void
    {
        // Create an external URL event (simulates old events with externalUrl=true)
        $event = EventFactory::new()->published()->create([
            'url' => 'https://www.facebook.com/events/123456789',
            'externalUrl' => true,
            'name' => 'External Event EN',
            'nimi' => 'External Event FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Load the edit form
        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        // Find the main edit form
        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('input[name*="[Name]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }

        self::assertNotNull($formNode, 'Expected to find the Sonata edit form');

        $form = $formNode->form();
        $values = $form->getPhpValues();

        // Detect the Sonata root form name
        $html = $crawler->html() ?? '';
        $root = null;
        if (1 === preg_match('/name="([^"]+)\[Name\]"/', $html, $m)) {
            $root = $m[1];
        }

        self::assertNotNull($root, 'Could not detect Sonata form root name');

        // Update a simple field to trigger form submission
        $values[$root]['Name'] = 'Updated External Event EN';
        // Simulate stray config submitted for externalUrl events (field not present in form)
        $values[$root]['backgroundEffectConfig'] = '{"unexpected":"value"}';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // Should succeed without "extra fields" validation error
        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302], 'Expected successful update response (200 or 302)');

        if (302 === $status) {
            $this->client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            // On 200 responses, ensure no form errors
            $this->client->assertSelectorNotExists(
                '.sonata-ba-form-error',
                'Form should not have form errors'
            );
            $this->client->assertSelectorNotExists(
                '.has-error',
                'Form validation errors detected'
            );
        }

        // Verify the update was persisted
        $em = $this->em();
        $em->clear();

        $reloaded = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Updated External Event EN', $reloaded->getName());
        self::assertTrue($reloaded->getExternalUrl());
    }

    /**
     * Test that updating an internal event with empty URL auto-generates one.
     */
    public function testUpdateInternalEventAutoGeneratesUrlWhenEmpty(): void
    {
        // Create an event with a URL, then clear it
        $event = EventFactory::new()->published()->create([
            'url' => 'original-url-'.uniqid('', true),
            'externalUrl' => false,
            'name' => 'Internal Event EN',
            'nimi' => 'Sisäinen Tapahtuma FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('input[name*="[Name]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }

        self::assertNotNull($formNode);

        $form = $formNode->form();
        $values = $form->getPhpValues();

        $html = $crawler->html() ?? '';
        $root = null;
        if (1 === preg_match('/name="([^"]+)\[Name\]"/', $html, $m)) {
            $root = $m[1];
        }

        self::assertNotNull($root);

        // Clear the URL - should trigger auto-generation
        $values[$root]['url'] = '';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302]);

        if (302 === $status) {
            $this->client->followRedirect();
        }

        $em = $this->em();
        $em->clear();

        $reloaded = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($reloaded);

        // URL should be auto-generated from Finnish name
        $slugger = static::getContainer()->get(SluggerInterface::class);
        $expectedUrl = $slugger
            ->slug((string) $reloaded->getNimi())
            ->lower()
            ->toString();
        self::assertSame($expectedUrl, $reloaded->getUrl());
    }

    /**
     * Test that external URL events can have their URL cleared (not auto-generated).
     */
    public function testUpdateExternalEventAllowsEmptyUrl(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'https://example.com/external-event',
            'externalUrl' => true,
            'name' => 'External Event EN',
            'nimi' => 'Ulkoinen Tapahtuma FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('input[name*="[Name]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }

        self::assertNotNull($formNode);

        $form = $formNode->form();
        $values = $form->getPhpValues();

        $html = $crawler->html() ?? '';
        $root = null;
        if (1 === preg_match('/name="([^"]+)\[Name\]"/', $html, $m)) {
            $root = $m[1];
        }

        self::assertNotNull($root);

        // Clear the URL - should stay empty for external events
        $values[$root]['url'] = '';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302]);

        if (302 === $status) {
            $this->client->followRedirect();
        }

        $em = $this->em();
        $em->clear();

        $reloaded = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($reloaded);

        // URL should remain empty (not auto-generated) for external events
        self::assertTrue(
            null === $reloaded->getUrl() || '' === $reloaded->getUrl(),
            'External event URL should remain empty when cleared'
        );
    }

    public function testCreateFormRendersRequiredFields(): void
    {
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', '/admin/event/create');
        $this->assertResponseIsSuccessful();

        $this->client->assertSelectorExists('input[name*="[Name]"]');
        $this->client->assertSelectorExists('input[name*="[Nimi]"]');
        $this->client->assertSelectorExists('select[name*="[type]"]');
    }

    public function testPrePersistAppliesDefaultsForEventType(): void
    {
        $admin = static::getContainer()->get('entropy.admin.event');
        $eventProxy = EventFactory::new()->withoutPersisting()->create([
            'type' => Event::TYPE_EVENT,
            'url' => null,
            'name' => 'Event Defaults EN',
            'nimi' => 'Oletus Tapahtuma FI',
        ]);
        $event = $eventProxy instanceof Proxy ? $eventProxy->_real(false) : $eventProxy;

        $admin->prePersist($event);

        self::assertNotNull($event->getNakkikone());
        self::assertTrue($event->getIncludeSaferSpaceGuidelines());

        $slugger = static::getContainer()->get(SluggerInterface::class);
        $expectedUrl = $slugger->slug((string) $event->getNimi())->lower()->toString();

        self::assertSame($expectedUrl, $event->getUrl());
        self::assertSame(
            'https://wiki.entropy.fi/index.php?title='.urlencode((string) $event->getNimi()),
            $event->getWikiPage(),
        );
    }

    public function testPrePersistSetsClubroomLocation(): void
    {
        $admin = static::getContainer()->get('entropy.admin.event');
        $eventProxy = EventFactory::new()->withoutPersisting()->create([
            'type' => Event::TYPE_CLUBROOM,
            'url' => null,
            'name' => 'Clubroom Event EN',
            'nimi' => 'Kerhotapahtuma FI',
        ]);
        $event = $eventProxy instanceof Proxy ? $eventProxy->_real(false) : $eventProxy;

        $admin->prePersist($event);

        $expectedLocation = $this->em()->getRepository(Location::class)->find(1);
        self::assertSame($expectedLocation, $event->getLocation());
        self::assertTrue($event->getIncludeSaferSpaceGuidelines());
    }

    public function testEditFormShowsTwigHelpForE30vTemplate(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-e30v-'.uniqid('', true),
            'template' => 'e30v.html.twig',
            'name' => 'Event E30V EN',
            'nimi' => 'Event E30V FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $this->client->assertSelectorExists('a[href="https://twig.symfony.com/"]');
    }

    public function testEditMenuIncludesRsvpAndNakkikoneLinks(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-menu-'.uniqid('', true),
            'name' => 'Event Menu EN',
            'nimi' => 'Event Menu FI',
            'eventDate' => new \DateTimeImmutable('2031-01-01 20:00'),
        ]);

        NakkikoneFactory::new()->enabled()->create(['event' => $event]);
        $menuRsvpEmail = 'menu-tester-'.bin2hex(random_bytes(4)).'@example.com';
        RSVPFactory::new()->create([
            'event' => $event,
            'firstName' => 'Menu',
            'lastName' => 'Tester',
            'email' => $menuRsvpEmail,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $this->client->assertSelectorTextContains('body', 'RSVP List');
        $this->client->assertSelectorTextContains('body', 'Nakkikone');
        $this->client->assertSelectorTextContains('body', 'Nakit');
        $this->client->assertSelectorTextContains('body', 'Printable Nakkilist');
    }

    public function testConfigureTabMenuReturnsEarlyForListAction(): void
    {
        $admin = static::getContainer()->get('entropy.admin.event');
        $menu = new MenuItem('root', new MenuFactory());

        $method = new \ReflectionMethod($admin, 'configureTabMenu');
        $method->setAccessible(true);
        $method->invoke($admin, $menu, 'list', null);

        self::assertCount(0, $menu->getChildren());
    }

    public function testBackgroundEffectConfigClearsForUnsupportedAndNullEffect(): void
    {
        $event = EventFactory::new()->published()->withBackgroundEffect('flowfields')->create([
            'url' => 'event-bg-effect-'.uniqid('', true),
            'name' => 'Event Background Effect EN',
            'nimi' => 'Event Background Effect FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $formNode = $this->findFormNodeByField($crawler, 'Name');
        self::assertNotNull($formNode, 'Expected to find the Sonata edit form');

        $form = $formNode->form();
        $values = $form->getPhpValues();
        $root = $this->detectRootName($crawler->html() ?? '', 'Name');

        $values[$root]['backgroundEffect'] = 'snow_mouse_dodge';
        unset($values[$root]['backgroundEffectConfig']);

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302], 'Expected successful update response (200 or 302)');
        if (302 === $status) {
            $this->client->followRedirect();
            $this->assertResponseIsSuccessful();
        }

        $em = $this->em();
        $em->clear();

        $updated = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($updated);
        self::assertSame('snow_mouse_dodge', $updated->getBackgroundEffect());
        self::assertNull($updated->getBackgroundEffectConfig());

        $crawler = $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $formNode = $this->findFormNodeByField($crawler, 'Name');
        self::assertNotNull($formNode, 'Expected to find the Sonata edit form');

        $form = $formNode->form();
        $values = $form->getPhpValues();
        $root = $this->detectRootName($crawler->html() ?? '', 'Name');

        unset($values[$root]['backgroundEffect']);
        $values[$root]['backgroundEffectConfig'] = '{"unused":"value"}';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302], 'Expected successful update response (200 or 302)');
        if (302 === $status) {
            $this->client->followRedirect();
            $this->assertResponseIsSuccessful();
        }

        $em->clear();
        $updated = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($updated);
        self::assertNull($updated->getBackgroundEffectConfig());
    }

    public function testPreSubmitGuardHandlesNonArrayPayload(): void
    {
        $admin = static::getContainer()->get('entropy.admin.event');
        $eventProxy = EventFactory::new()->create();
        $event = $eventProxy instanceof Proxy ? $eventProxy->object() : $eventProxy;

        $admin->setSubject($event);

        $formBuilder = $admin->getFormBuilder();
        $form = $formBuilder->getForm();

        $listener = null;
        foreach ($formBuilder->getEventDispatcher()->getListeners(FormEvents::PRE_SUBMIT) as $candidate) {
            if (!$candidate instanceof \Closure) {
                continue;
            }
            $ref = new \ReflectionFunction($candidate);
            $file = $ref->getFileName() ?? '';
            if (str_contains($file, 'src/Admin/EventAdmin.php')) {
                $listener = $candidate;
                break;
            }
        }

        self::assertNotNull($listener, 'Expected to find EventAdmin PRE_SUBMIT listener');

        $eventPayload = new FormEvent($form, 'not-an-array');
        $listener($eventPayload);

        self::assertSame('not-an-array', $eventPayload->getData());
    }

    public function testPreValidateAddsWarningsForInvalidDates(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-warning-'.uniqid('', true),
            'name' => 'Event Warning EN',
            'nimi' => 'Event Warning FI',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/edit");
        $this->assertResponseIsSuccessful();

        $requestStack = static::getContainer()->get(RequestStack::class);
        $request = $requestStack->getCurrentRequest();
        if (null === $request) {
            $request = \Symfony\Component\HttpFoundation\Request::create(
                "/admin/event/{$event->getId()}/edit",
            );
            $requestStack->push($request);
        }

        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->start();

        $event->setTicketsEnabled(true);
        $event->setTicketPresaleStart(new \DateTimeImmutable('2030-01-02 10:00'));
        $event->setTicketPresaleEnd(new \DateTimeImmutable('2030-01-02 09:00'));
        $event->setEventDate(new \DateTimeImmutable('2030-02-02 12:00'));
        $event->setUntil(new \DateTimeImmutable('2030-02-02 11:00'));

        $admin = static::getContainer()->get('entropy.admin.event');
        $admin->preValidate($event);

        $warnings = $session->getFlashBag()->peek('warning');
        self::assertContains('Presale end date must be after start date', $warnings);
        self::assertContains('Event stop time must be after start time', $warnings);
    }

    private function findFormNodeByField(Crawler $crawler, string $fieldName): ?Crawler
    {
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            $selector = \sprintf('input[name*="[%s]"]', $fieldName);
            if ($candidate->filter($selector)->count() > 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function detectRootName(string $html, string $fieldName): string
    {
        $pattern = \sprintf('/name="([^"]+)\\[%s\\]"/', preg_quote($fieldName, '/'));
        if (1 === preg_match($pattern, $html, $m)) {
            return $m[1];
        }

        throw new \RuntimeException(\sprintf('Could not detect Sonata form root name for %s', $fieldName));
    }

    private function setDateTimeField(
        array $values,
        string $root,
        string $field,
        \DateTimeImmutable $value,
    ): array {
        if (!\array_key_exists($root, $values)) {
            return $values;
        }

        $targetField = $field;
        if (!\array_key_exists($targetField, $values[$root])) {
            foreach (array_keys($values[$root]) as $key) {
                if (0 === strcasecmp((string) $key, $field)) {
                    $targetField = (string) $key;
                    break;
                }
            }
        }

        if (!\array_key_exists($targetField, $values[$root])) {
            return $values;
        }

        $current = $values[$root][$targetField] ?? null;
        if (\is_array($current)) {
            if (isset($current['date']) && \is_array($current['date'])) {
                if (\array_key_exists('year', $current['date'])) {
                    $values[$root][$targetField]['date']['year'] = $value->format('Y');
                }
                if (\array_key_exists('month', $current['date'])) {
                    $values[$root][$targetField]['date']['month'] = $value->format('m');
                }
                if (\array_key_exists('day', $current['date'])) {
                    $values[$root][$targetField]['date']['day'] = $value->format('d');
                }
            }
            if (isset($current['time']) && \is_array($current['time'])) {
                if (\array_key_exists('hour', $current['time'])) {
                    $values[$root][$targetField]['time']['hour'] = $value->format('H');
                }
                if (\array_key_exists('minute', $current['time'])) {
                    $values[$root][$targetField]['time']['minute'] = $value->format('i');
                }
                if (\array_key_exists('second', $current['time'])) {
                    $values[$root][$targetField]['time']['second'] = $value->format('s');
                }
            }

            return $values;
        }

        $values[$root][$targetField] = $value->format('Y-m-d H:i');

        return $values;
    }
}
