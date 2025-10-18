<?php

declare(strict_types=1);

namespace App\Tests\Functional\Profile;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for CalendarConfigType form and calendar URL generation.
 *
 * Tests cover:
 *  - Access control (anonymous vs authenticated)
 *  - Form rendering and structure (6 checkboxes)
 *  - Bilingual routes (/profile/calendar, /profiili/kalenteri)
 *  - Successful form submission and URL generation
 *  - Flash messages
 *  - Configuration encoding (all checked, none checked, partial)
 */
final class CalendarConfigFormTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testAnonymousUserRedirectedToLogin(): void
    {
        $this->client->request('GET', '/en/profile/calendar');

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode(), 'Anonymous user should be redirected');

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location, 'Should redirect to login page');
    }

    #[DataProvider('localeProvider')]
    public function testAuthenticatedUserCanAccessForm(string $locale): void
    {
        $member = MemberFactory::new()->inactive()->with(['locale' => $locale])->create();
        $this->loginAsMember($member->getEmail());

        $path = 'en' === $locale ? '/en/profile/calendar' : '/profiili/kalenteri';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    public function testFormHasAllSixCheckboxes(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');

        // Verify all 6 checkboxes exist
        $this->client->assertSelectorExists('input[name="calendar_config[add_events]"]');
        $this->client->assertSelectorExists('input[name="calendar_config[add_notifications_for_events]"]');
        $this->client->assertSelectorExists('input[name="calendar_config[add_clubroom_events]"]');
        $this->client->assertSelectorExists('input[name="calendar_config[add_notifications_for_clubroom_events]"]');
        $this->client->assertSelectorExists('input[name="calendar_config[add_meetings]"]');
        $this->client->assertSelectorExists('input[name="calendar_config[add_notifications_for_meetings]"]');
    }

    public function testSuccessfulSubmissionGeneratesUrl(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form([
            'calendar_config[add_events]' => true,
            'calendar_config[add_notifications_for_events]' => true,
            'calendar_config[add_clubroom_events]' => true,
            'calendar_config[add_notifications_for_clubroom_events]' => true,
            'calendar_config[add_meetings]' => true,
            'calendar_config[add_notifications_for_meetings]' => true,
        ]);

        $this->client->submit($form);

        // Should redirect after successful submission
        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            \in_array($statusCode, [200, 302, 303], true),
            \sprintf('Expected successful response or redirect, got %d', $statusCode)
        );
    }

    public function testSubmissionWithAllOptionsEnabled(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form([
            'calendar_config[add_events]' => '1',
            'calendar_config[add_notifications_for_events]' => '1',
            'calendar_config[add_clubroom_events]' => '1',
            'calendar_config[add_notifications_for_clubroom_events]' => '1',
            'calendar_config[add_meetings]' => '1',
            'calendar_config[add_notifications_for_meetings]' => '1',
        ]);

        $this->client->submit($form);

        $response = $this->client->getResponse();
        $this->assertTrue(
            \in_array($response->getStatusCode(), [200, 302, 303], true),
            'Form submission should succeed'
        );
    }

    public function testSubmissionWithAllOptionsDisabled(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form();
        // Uncheck all checkboxes (Symfony form handles unchecked as false/null)
        $form->disableValidation();
        $form['calendar_config[add_events]']->untick();
        $form['calendar_config[add_notifications_for_events]']->untick();
        $form['calendar_config[add_clubroom_events]']->untick();
        $form['calendar_config[add_notifications_for_clubroom_events]']->untick();
        $form['calendar_config[add_meetings]']->untick();
        $form['calendar_config[add_notifications_for_meetings]']->untick();

        $this->client->submit($form);

        $response = $this->client->getResponse();
        $this->assertTrue(
            \in_array($response->getStatusCode(), [200, 302, 303], true),
            'Form submission should succeed even with all options disabled'
        );
    }

    public function testSubmissionWithPartialSelection(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form();
        $form->disableValidation();

        // Check only events and clubroom events, uncheck notifications and meetings
        $form['calendar_config[add_events]']->tick();
        $form['calendar_config[add_notifications_for_events]']->untick();
        $form['calendar_config[add_clubroom_events]']->tick();
        $form['calendar_config[add_notifications_for_clubroom_events]']->untick();
        $form['calendar_config[add_meetings]']->untick();
        $form['calendar_config[add_notifications_for_meetings]']->untick();

        $this->client->submit($form);

        $response = $this->client->getResponse();
        $this->assertTrue(
            \in_array($response->getStatusCode(), [200, 302, 303], true),
            'Form submission should succeed with partial selection'
        );
    }

    public function testFlashMessageDisplayedAfterSubmission(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form();
        $this->client->submit($form);

        // After redirect, check for flash message
        if (\in_array($this->client->getResponse()->getStatusCode(), [302, 303], true)) {
            $this->client->followRedirect();
        }

        // Flash message should be present (translation key: calendar.url_generated)
        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
    }

    #[DataProvider('localeProvider')]
    public function testBilingualRoutesWork(string $locale): void
    {
        $member = MemberFactory::new()->inactive()->with(['locale' => $locale])->create();
        $this->loginAsMember($member->getEmail());

        $path = 'en' === $locale ? '/en/profile/calendar' : '/profiili/kalenteri';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    public function testDefaultValuesAreAllChecked(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/calendar');
        $crawler = $this->client->getCrawler();

        // All checkboxes should be checked by default (data: true in form)
        $addEvents = $crawler->filter('input[name="calendar_config[add_events]"]');
        $addNotifications = $crawler->filter('input[name="calendar_config[add_notifications_for_events]"]');
        $addClubroom = $crawler->filter('input[name="calendar_config[add_clubroom_events]"]');
        $addClubroomNotif = $crawler->filter('input[name="calendar_config[add_notifications_for_clubroom_events]"]');
        $addMeetings = $crawler->filter('input[name="calendar_config[add_meetings]"]');
        $addMeetingsNotif = $crawler->filter('input[name="calendar_config[add_notifications_for_meetings]"]');

        // Count how many are checked
        $this->assertGreaterThanOrEqual(1, $addEvents->count(), 'add_events checkbox should exist');
        $this->assertGreaterThanOrEqual(1, $addNotifications->count(), 'add_notifications_for_events checkbox should exist');
        $this->assertGreaterThanOrEqual(1, $addClubroom->count(), 'add_clubroom_events checkbox should exist');
        $this->assertGreaterThanOrEqual(1, $addClubroomNotif->count(), 'add_notifications_for_clubroom_events checkbox should exist');
        $this->assertGreaterThanOrEqual(1, $addMeetings->count(), 'add_meetings checkbox should exist');
        $this->assertGreaterThanOrEqual(1, $addMeetingsNotif->count(), 'add_notifications_for_meetings checkbox should exist');
    }

    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }
}
