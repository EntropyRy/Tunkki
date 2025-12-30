<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Factory\NakkiBookingFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkikoneFactory;
use App\Factory\RSVPFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

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
            $content = $this->client->getResponse()->getContent() ?? '';
            self::assertStringNotContainsString('has-error', $content, 'Form validation errors detected in response HTML');
            self::assertStringNotContainsString('sonata-ba-form-error', $content, 'Form errors detected in response HTML');
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

        // Create at least one RSVP so the table renders and is sortable.
        $rsvp = RSVPFactory::new()->create([
            'event' => $event,
            'firstName' => 'Test',
            'lastName' => 'Person',
            'email' => 'test.person@example.com',
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

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // Should succeed without "extra fields" validation error
        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 302], 'Expected successful update response (200 or 302)');

        if (302 === $status) {
            $this->client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            // On 200 responses, ensure no form errors
            $content = $this->client->getResponse()->getContent() ?? '';
            self::assertStringNotContainsString(
                'ylimääräisiä kenttiä',
                $content,
                'Form should not have "extra fields" error (Finnish)'
            );
            self::assertStringNotContainsString(
                'extra fields',
                $content,
                'Form should not have "extra fields" error (English)'
            );
            self::assertStringNotContainsString('has-error', $content, 'Form validation errors detected');
        }

        // Verify the update was persisted
        $em = $this->em();
        $em->clear();

        $reloaded = $em->getRepository(Event::class)->find($event->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Updated External Event EN', $reloaded->getName());
        self::assertTrue($reloaded->getExternalUrl());
    }
}
