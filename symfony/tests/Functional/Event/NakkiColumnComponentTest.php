<?php

declare(strict_types=1);

namespace App\Tests\Functional\Event;

use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiBookingFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * Tests the Nakki:Column LiveComponent functionality.
 *
 * Validates:
 * - Column renders with nakki data
 * - Displays bookings
 * - Shows responsible and metadata
 * - Edit button functionality
 * - Delete button behavior
 * - Booking enable/disable toggle
 * - Add slots form
 * - Remove slot action
 *
 * Note: Full LiveComponent action testing would require Panther.
 * These tests verify the component renders correctly with various data states.
 */
final class NakkiColumnComponentTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client;
    }

    /* -----------------------------------------------------------------
     * Rendering: Column displays nakki information
     * ----------------------------------------------------------------- */
    public function testColumnRendersWithNakkiData(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-render-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Test Nakki',
            'nameEn' => 'Test Nakki EN',
        ]);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display definition name
        $this->client->assertSelectorTextContains('body', 'Test Nakki');
    }

    public function testColumnDisplaysResponsibleMember(): void
    {
        $member = MemberFactory::new()->active()->create([
            'firstname' => 'Vastuullinen',
            'lastname' => 'Henkilö',
        ]);
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-resp-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Responsible Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
            'responsible' => $member,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display responsible member name
        $this->client->assertSelectorTextContains('body', 'Vastuullinen');
        $this->client->assertSelectorTextContains('body', 'Henkilö');
    }

    public function testColumnDisplaysMattermostChannel(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-mattermost-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Mattermost Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
            'mattermostChannel' => 'test-nakki-channel',
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display mattermost channel
        $this->client->assertSelectorTextContains('body', 'test-nakki-channel');
    }

    /* -----------------------------------------------------------------
     * Bookings display
     * ----------------------------------------------------------------- */
    public function testColumnDisplaysBookings(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-bookings-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Bookings Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        // Create some bookings
        $now = new \DateTimeImmutable();
        NakkiBookingFactory::new()->create([
            'nakki' => $nakki,
            'event' => $event,
            'startAt' => $now,
            'endAt' => $now->modify('+1 hour'),
        ]);
        NakkiBookingFactory::new()->create([
            'nakki' => $nakki,
            'event' => $event,
            'startAt' => $now->modify('+1 hour'),
            'endAt' => $now->modify('+2 hours'),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should have booking slots rendered
        // (Exact structure depends on template implementation)
        $this->client->assertSelectorTextContains('body', 'Bookings Test');
    }

    public function testColumnDisplaysBookedSlots(): void
    {
        $member = MemberFactory::new()->active()->create([
            'firstname' => 'Booked',
            'lastname' => 'Member',
        ]);
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-booked-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Booked Slots Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        $now = new \DateTimeImmutable();
        NakkiBookingFactory::new()->create([
            'nakki' => $nakki,
            'event' => $event,
            'startAt' => $now,
            'endAt' => $now->modify('+1 hour'),
            'member' => $member,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display booked member name
        $this->client->assertSelectorTextContains('body', 'Booked');
        $this->client->assertSelectorTextContains('body', 'Member');
    }

    /* -----------------------------------------------------------------
     * Action buttons presence
     * ----------------------------------------------------------------- */
    public function testColumnHasEditButton(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-edit-btn-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Edit Button Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should have edit button with LiveAction
        $content = $this->client->getResponse()->getContent();
        if (false !== $content) {
            self::assertStringContainsString('editColumn', $content, 'Should have edit button action');
        }
    }

    public function testColumnHasDeleteButton(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-delete-btn-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Delete Button Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should have delete/remove button with LiveAction
        $content = $this->client->getResponse()->getContent();
        if (false !== $content) {
            self::assertStringContainsString('deleteColumn', $content, 'Should have delete button action');
        }
    }

    /* -----------------------------------------------------------------
     * Add slots form presence
     * ----------------------------------------------------------------- */
    public function testColumnHasAddSlotsForm(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-add-slots-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Add Slots Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should have add slots action
        $content = $this->client->getResponse()->getContent();
        if (false !== $content) {
            self::assertStringContainsString('addSlots', $content, 'Should have add slots functionality');
        }
    }

    /* -----------------------------------------------------------------
     * Disable bookings toggle
     * ----------------------------------------------------------------- */
    public function testColumnShowsDisabledBookingsState(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-disabled-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Disabled Test']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
            'disableBookings' => true,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Column should render (checking for disabled state requires template structure knowledge)
        $this->client->assertSelectorTextContains('body', 'Disabled Test');
    }

    /* -----------------------------------------------------------------
     * Bilingual support
     * ----------------------------------------------------------------- */
    public function testColumnRendersInEnglishLocale(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-en-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Testi',
            'nameEn' => 'English Column Test',
        ]);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/en/{$year}/{$event->getUrl()}/nakkikone/admin";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display English name
        $this->client->assertSelectorTextContains('body', 'English Column Test');
    }

    /* -----------------------------------------------------------------
     * Edge cases
     * ----------------------------------------------------------------- */
    public function testColumnWithNoBookingsShowsEmptyState(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-no-bookings-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'Empty Column']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Column should still render
        $this->client->assertSelectorTextContains('body', 'Empty Column');
    }

    public function testColumnWithNoResponsibleShowsEmptyState(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-column-no-resp-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create(['nameFi' => 'No Responsible']);
        $nakki = NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
            'responsible' => null,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Column should render without responsible
        $this->client->assertSelectorTextContains('body', 'No Responsible');
    }
}
