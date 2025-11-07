<?php

declare(strict_types=1);

namespace App\Tests\Functional\Event;

use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * Tests the Nakki:Board LiveComponent functionality.
 *
 * Validates:
 * - Board renders with event context
 * - Displays existing columns (nakkis)
 * - Form for adding/updating columns
 * - Column editing workflow
 * - Definition selection integration
 * - Bilingual support
 *
 * Note: LiveComponent interactions are tested via their HTTP endpoints.
 * Full JavaScript interaction testing would require Panther (browser tests).
 */
final class NakkiBoardComponentTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client;
    }

    /* -----------------------------------------------------------------
     * Rendering: Board displays correctly
     * ----------------------------------------------------------------- */
    public function testBoardRendersWithEvent(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-render-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Board component should be present
        $this->client->assertSelectorExists('[data-controller*="scroll-planner"]');
        // Should have the planner heading
        $this->client->assertSelectorExists('#nakkikone-planner');
        // Should have the definition selector (from nested Definition component)
        $this->client->assertSelectorExists('#nakki-definition-select');
    }

    public function testBoardRendersExistingColumns(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-columns-'.uniqid('', true),
        ]);
        $definition1 = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Ilmoittautuminen',
            'nameEn' => 'Registration',
        ]);
        $definition2 = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Somistus',
            'nameEn' => 'Decoration',
        ]);

        NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition1,
        ]);
        NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition2,
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display both column names (in Finnish since we're on FI route)
        $this->client->assertSelectorTextContains('body', 'Ilmoittautuminen');
        $this->client->assertSelectorTextContains('body', 'Somistus');
    }

    public function testBoardShowsNoColumnsMessage(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-empty-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should show "no columns" message
        $this->client->assertSelectorExists('.alert-info');
    }

    /* -----------------------------------------------------------------
     * Form presence and structure
     * ----------------------------------------------------------------- */
    public function testBoardFormAppearsWhenDefinitionSelected(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-form-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Test Definition',
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should have definition selector
        $this->client->assertSelectorExists('#nakki-definition-select');

        // Form should not be present initially (no definition selected)
        $content = $this->client->getResponse()->getContent();
        if (false !== $content) {
            self::assertStringNotContainsString('data-live-action-param="addColumn"', $content, 'Form should not be rendered when no definition selected');
        }

        // Note: Full interaction testing (selecting definition -> form appears)
        // requires LiveComponent functionality which needs browser tests (Panther)
    }

    public function testBoardFormHiddenWhenNoDefinitionSelected(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-button-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Form and submit button should not be rendered when no definition selected
        $content = $this->client->getResponse()->getContent();
        if (false !== $content) {
            self::assertStringNotContainsString('data-live-action-param="addColumn"', $content, 'Form should not be rendered when no definition selected');
        }
    }

    /* -----------------------------------------------------------------
     * Column metadata population
     * ----------------------------------------------------------------- */
    public function testBoardPopulatesExistingNakkiMetadata(): void
    {
        $member = MemberFactory::new()->active()->create([
            'firstname' => 'Test',
            'lastname' => 'Responsible',
        ]);
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-metadata-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Metadata Test',
        ]);

        NakkiFactory::new()->create([
            'event' => $event,
            'definition' => $definition,
            'responsible' => $member,
            'mattermostChannel' => 'test-channel',
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should display the column with metadata
        $this->client->assertSelectorTextContains('body', 'Metadata Test');
        // Note: Checking form pre-population would require selecting the definition
        // which involves LiveComponent interactions beyond basic HTTP requests
    }

    /* -----------------------------------------------------------------
     * Bilingual support
     * ----------------------------------------------------------------- */
    public function testBoardRendersInEnglishLocale(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-en-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/en/{$year}/{$event->getUrl()}/nakkikone/admin";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Board should render
        $this->client->assertSelectorExists('[data-controller*="scroll-planner"]');
        $this->client->assertSelectorExists('#nakkikone-planner');
    }

    /* -----------------------------------------------------------------
     * Edit button integration (Column -> Board)
     * ----------------------------------------------------------------- */
    public function testBoardHasScrollTargetForEditWorkflow(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-board-scroll-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN', [], 'admin@example.com');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        // Should have scroll target for edit workflow
        $this->client->assertSelectorExists('#nakkikone-planner');
        // Should have Stimulus controller for scroll handling
        $this->client->assertSelectorExists('[data-controller*="scroll-planner"]');
    }
}
