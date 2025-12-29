<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Location;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Sqids\Sqids;

/**
 * Functional tests for CalendarController.
 *
 * Covers:
 * - eventCalendarConfig: Calendar configuration form for authenticated users
 * - eventCalendar: ICS calendar file generation with event filtering
 */
final class CalendarControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    /* -----------------------------------------------------------------------
     * eventCalendarConfig Tests
     * ----------------------------------------------------------------------- */

    /**
     * Test that unauthenticated users are redirected to login.
     */
    #[DataProvider('localeProvider')]
    public function testCalendarConfigRedirectsUnauthenticatedUser(string $locale): void
    {
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/calendar' : '/profiili/kalenteri';
        $this->client->request('GET', $path);

        // Should redirect to login
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('login', $location);
    }

    /**
     * Test that authenticated users can access the calendar config page.
     */
    #[DataProvider('localeProvider')]
    public function testCalendarConfigPageLoadsForAuthenticatedUser(string $locale): void
    {
        $this->loginAsMember();
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/calendar' : '/profiili/kalenteri';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    /**
     * Test calendar config form submission generates a URL.
     */
    #[DataProvider('localeProvider')]
    public function testCalendarConfigFormSubmissionGeneratesUrl(string $locale): void
    {
        $this->loginAsMember();
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/calendar' : '/profiili/kalenteri';
        $crawler = $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        // Submit the form with all options enabled
        $form = $crawler->filter('form')->form([
            'calendar_config[add_events]' => true,
            'calendar_config[add_notifications_for_events]' => true,
            'calendar_config[add_clubroom_events]' => true,
            'calendar_config[add_notifications_for_clubroom_events]' => true,
            'calendar_config[add_meetings]' => true,
            'calendar_config[add_notifications_for_meetings]' => true,
        ]);

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        // Check that URL input is now visible
        $this->client->assertSelectorExists('input[type="text"]');
    }

    /**
     * Test calendar config form submission with partial options.
     */
    #[DataProvider('localeProvider')]
    public function testCalendarConfigFormSubmissionWithPartialOptions(string $locale): void
    {
        $this->loginAsMember();
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/calendar' : '/profiili/kalenteri';
        $crawler = $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        // Submit with only events enabled (no clubroom, no meetings)
        $form = $crawler->filter('form')->form([
            'calendar_config[add_events]' => true,
            'calendar_config[add_notifications_for_events]' => false,
            'calendar_config[add_clubroom_events]' => false,
            'calendar_config[add_notifications_for_clubroom_events]' => false,
            'calendar_config[add_meetings]' => false,
            'calendar_config[add_notifications_for_meetings]' => false,
        ]);

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('input[type="text"]');
    }

    /* -----------------------------------------------------------------------
     * eventCalendar (ICS generation) Tests
     * ----------------------------------------------------------------------- */

    /**
     * Test basic ICS calendar generation with an event.
     */
    #[DataProvider('localeProvider')]
    public function testEventCalendarGeneratesIcs(string $locale): void
    {
        $this->seedClientHome($locale);

        // Create a published event
        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Testi Tapahtuma',
            'name' => 'Test Event',
            'url' => 'test-event-'.uniqid('', true),
        ]);
        $this->em()->flush();

        // Generate calendar hash with events enabled
        $sqid = new Sqids();
        // Format: [add_events, notify_events, add_clubroom, notify_clubroom, add_meetings, notify_meetings, user_id]
        $hash = $sqid->encode([1, 1, 0, 0, 0, 0, 999]);

        $path = 'en' === $locale ? "/en/{$hash}/calendar.ics" : "/{$hash}/kalenteri.ics";
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();
        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="entropy.ics"', $response->headers->get('Content-Disposition') ?? '');

        // Verify ICS content structure
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }

    /**
     * Test ICS generation filters events by type.
     */
    public function testEventCalendarFiltersEventsByType(): void
    {
        $this->seedClientHome('fi');

        // Create different event types
        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Party Tapahtuma',
            'name' => 'Party Event',
            'url' => 'party-'.uniqid('', true),
        ]);

        EventFactory::new()->published()->create([
            'type' => 'clubroom',
            'nimi' => 'Kerhokerta',
            'name' => 'Clubroom Session',
            'url' => 'clubroom-'.uniqid('', true),
        ]);

        EventFactory::new()->published()->create([
            'type' => 'meeting',
            'nimi' => 'Kokous',
            'name' => 'Meeting',
            'url' => 'meeting-'.uniqid('', true),
        ]);

        EventFactory::new()->published()->create([
            'type' => 'stream',
            'nimi' => 'Striimi',
            'name' => 'Stream',
            'url' => 'stream-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Test: Only events enabled
        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]); // Only events, no notifications

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Party Tapahtuma', $content);
        $this->assertStringNotContainsString('Kerhokerta', $content);
        $this->assertStringNotContainsString('Kokous', $content);
    }

    /**
     * Test ICS generation includes clubroom and stream events.
     */
    public function testEventCalendarIncludesClubroomAndStreamEvents(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'clubroom',
            'nimi' => 'Kerhokerta',
            'name' => 'Clubroom Session',
            'url' => 'clubroom-'.uniqid('', true),
        ]);

        EventFactory::new()->published()->create([
            'type' => 'stream',
            'nimi' => 'Striimi',
            'name' => 'Stream Event',
            'url' => 'stream-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Enable clubroom events (includes stream)
        $sqid = new Sqids();
        $hash = $sqid->encode([0, 0, 1, 0, 0, 0, 999]); // Only clubroom/stream

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Kerhokerta', $content);
        $this->assertStringContainsString('Striimi', $content);
    }

    /**
     * Test ICS generation includes meetings.
     */
    public function testEventCalendarIncludesMeetings(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'meeting',
            'nimi' => 'Hallituksen kokous',
            'name' => 'Board Meeting',
            'url' => 'meeting-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Enable only meetings
        $sqid = new Sqids();
        $hash = $sqid->encode([0, 0, 0, 0, 1, 0, 999]); // Only meetings

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Hallituksen kokous', $content);
    }

    /**
     * Test ICS generation with notifications enabled adds alarms.
     */
    public function testEventCalendarWithNotificationsAddsAlarms(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Alarm Tapahtuma',
            'name' => 'Alarm Event',
            'url' => 'alarm-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Enable events with notifications
        $sqid = new Sqids();
        $hash = $sqid->encode([1, 1, 0, 0, 0, 0, 999]); // Events + notifications

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('BEGIN:VALARM', $content);
        $this->assertStringContainsString('TRIGGER', $content);
        $this->assertStringContainsString('Muistutus huomisesta Entropy tapahtumasta', $content);
    }

    /**
     * Test ICS generation with English locale uses English alarm text.
     */
    public function testEventCalendarEnglishLocaleUsesEnglishAlarmText(): void
    {
        $this->seedClientHome('en');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'En Alarm Tapahtuma',
            'name' => 'En Alarm Event',
            'url' => 'en-alarm-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Enable events with notifications
        $sqid = new Sqids();
        $hash = $sqid->encode([1, 1, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/en/{$hash}/calendar.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('BEGIN:VALARM', $content);
        $this->assertStringContainsString('Reminder for Entropy event tommorrow', $content);
    }

    /**
     * Test ICS generation with physical location only.
     */
    public function testEventCalendarWithPhysicalLocationOnly(): void
    {
        $this->seedClientHome('fi');

        // Create location
        $location = new Location();
        $location->setName('Oranssi');
        $location->setNameEn('Oranssi EN');
        $location->setStreetAddress('Kumpulantie 1');
        $this->em()->persist($location);

        $event = EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Paikka Tapahtuma',
            'name' => 'Location Event',
            'url' => 'location-'.uniqid('', true),
        ]);

        // Set location after creation
        $event->setLocation($location);
        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('LOCATION', $content);
        $this->assertStringContainsString('Kumpulantie 1', $content);
    }

    /**
     * Test ICS generation with online meeting URL only.
     */
    public function testEventCalendarWithOnlineMeetingUrlOnly(): void
    {
        $this->seedClientHome('fi');

        $event = EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Online Tapahtuma',
            'name' => 'Online Event',
            'url' => 'online-'.uniqid('', true),
        ]);

        $event->setWebMeetingUrl('https://meet.example.com/room123');
        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('LOCATION', $content);
        $this->assertStringContainsString('https://meet.example.com/room123', $content);
    }

    /**
     * Test ICS generation with both physical location and online meeting URL.
     */
    public function testEventCalendarWithBothPhysicalAndOnlineLocation(): void
    {
        $this->seedClientHome('fi');

        // Create location
        $location = new Location();
        $location->setName('Hybrid Venue');
        $location->setNameEn('Hybrid Venue EN');
        $location->setStreetAddress('Hybridikatu 5');
        $this->em()->persist($location);

        $event = EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Hybrid Tapahtuma',
            'name' => 'Hybrid Event',
            'url' => 'hybrid-'.uniqid('', true),
        ]);

        $event->setLocation($location);
        $event->setWebMeetingUrl('https://meet.example.com/hybrid');
        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('LOCATION', $content);
        // Should contain composite location with physical and online info
        // Format: "Venue Name – Street Address (Online: URL)"
        // Note: ICS folds long lines at 75 chars, so we check individual parts
        $this->assertStringContainsString('Hybrid Venue', $content);
        $this->assertStringContainsString('Hybridikatu 5', $content);
        $this->assertStringContainsString('(Online:', $content);
        // URL may be folded across lines, check the recognizable part
        $this->assertStringContainsString('meet.example.com', $content);
    }

    /**
     * Test ICS generation with no location or online URL.
     */
    public function testEventCalendarWithNoLocation(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'No Location Tapahtuma',
            'name' => 'No Location Event',
            'url' => 'noloc-'.uniqid('', true),
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        // Should still have valid ICS structure
        $this->assertStringContainsString('BEGIN:VEVENT', $content);
        $this->assertStringContainsString('END:VEVENT', $content);
    }

    /**
     * Test ICS with empty hash generates calendar but with no matching events.
     */
    public function testEventCalendarWithDisabledFiltersGeneratesEmptyCalendar(): void
    {
        $this->seedClientHome('fi');

        // Create an event that won't match any filter
        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Hidden Event',
            'name' => 'Hidden Event',
            'url' => 'hidden-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // All filters disabled
        $sqid = new Sqids();
        $hash = $sqid->encode([0, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        // No VEVENT should be present if all filters are off
        $this->assertStringNotContainsString('Hidden Event', $content);
    }

    /**
     * Test ICS generation with all event types enabled.
     */
    public function testEventCalendarWithAllTypesEnabled(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'All Types - Event',
            'name' => 'All Types - Event',
            'url' => 'all-event-'.uniqid('', true),
        ]);

        EventFactory::new()->published()->create([
            'type' => 'clubroom',
            'nimi' => 'All Types - Clubroom',
            'name' => 'All Types - Clubroom',
            'url' => 'all-clubroom-'.uniqid('', true),
        ]);

        EventFactory::new()->published()->create([
            'type' => 'meeting',
            'nimi' => 'All Types - Meeting',
            'name' => 'All Types - Meeting',
            'url' => 'all-meeting-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // All types enabled with notifications
        $sqid = new Sqids();
        $hash = $sqid->encode([1, 1, 1, 1, 1, 1, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('All Types - Event', $content);
        $this->assertStringContainsString('All Types - Clubroom', $content);
        $this->assertStringContainsString('All Types - Meeting', $content);
        // Should have alarms for all
        $eventCount = substr_count($content, 'BEGIN:VEVENT');
        $this->assertGreaterThanOrEqual(3, $eventCount);
    }

    /**
     * Test ICS includes timezone information.
     */
    public function testEventCalendarIncludesTimezone(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'TZ Event',
            'name' => 'TZ Event',
            'url' => 'tz-'.uniqid('', true),
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('VTIMEZONE', $content);
        $this->assertStringContainsString('Europe/Helsinki', $content);
    }

    /**
     * Test ICS event includes event URL.
     */
    public function testEventCalendarIncludesEventUrl(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'URL Event',
            'name' => 'URL Event EN',
            'url' => 'url-event-'.uniqid('', true),
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('URL:', $content);
    }

    /**
     * Test English locale uses English event names.
     */
    public function testEventCalendarEnglishLocaleUsesEnglishNames(): void
    {
        $this->seedClientHome('en');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Finnish Name',
            'name' => 'English Name',
            'url' => 'en-name-'.uniqid('', true),
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/en/{$hash}/calendar.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('English Name', $content);
    }

    /**
     * Test Finnish locale uses Finnish event names.
     */
    public function testEventCalendarFinnishLocaleUsesFinnishNames(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'Suomenkielinen Nimi',
            'name' => 'English Language Name',
            'url' => 'fi-name-'.uniqid('', true),
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Suomenkielinen Nimi', $content);
    }

    /**
     * Test ICS generation with clubroom notifications enabled.
     */
    public function testEventCalendarClubroomWithNotifications(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'clubroom',
            'nimi' => 'Kerho Alarm',
            'name' => 'Club Alarm',
            'url' => 'club-alarm-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Enable clubroom with notifications
        $sqid = new Sqids();
        $hash = $sqid->encode([0, 0, 1, 1, 0, 0, 999]); // Clubroom + notifications

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Kerho Alarm', $content);
        $this->assertStringContainsString('BEGIN:VALARM', $content);
    }

    /**
     * Test ICS generation with meeting notifications enabled.
     */
    public function testEventCalendarMeetingWithNotifications(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'meeting',
            'nimi' => 'Kokous Alarm',
            'name' => 'Meeting Alarm',
            'url' => 'meeting-alarm-'.uniqid('', true),
        ]);

        $this->em()->flush();

        // Enable meetings with notifications
        $sqid = new Sqids();
        $hash = $sqid->encode([0, 0, 0, 0, 1, 1, 999]); // Meetings + notifications

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Kokous Alarm', $content);
        $this->assertStringContainsString('BEGIN:VALARM', $content);
    }

    /**
     * Test ICS generation with event that has until date (multiday event).
     */
    public function testEventCalendarWithMultidayEvent(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->multiday(3)->create([
            'type' => 'event',
            'nimi' => 'Kolmen paivan tapahtuma',
            'name' => 'Three Day Event',
            'url' => 'multiday-'.uniqid('', true),
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Kolmen paivan tapahtuma', $content);
        // Should have both DTSTART and DTEND
        $this->assertStringContainsString('DTSTART', $content);
        $this->assertStringContainsString('DTEND', $content);
    }

    /**
     * Test ICS generation with event having HTML content (should be stripped).
     */
    public function testEventCalendarStripsHtmlFromDescription(): void
    {
        $this->seedClientHome('fi');

        EventFactory::new()->published()->create([
            'type' => 'event',
            'nimi' => 'HTML Tapahtuma',
            'name' => 'HTML Event',
            'url' => 'html-'.uniqid('', true),
            'Sisallys' => '<p>Test <strong>content</strong> with <a href="test">links</a></p>',
            'Content' => '<p>Test <strong>content</strong> with <a href="test">links</a></p>',
        ]);

        $this->em()->flush();

        $sqid = new Sqids();
        $hash = $sqid->encode([1, 0, 0, 0, 0, 0, 999]);

        $this->client->request('GET', "/{$hash}/kalenteri.ics");
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        // HTML should be stripped
        $this->assertStringNotContainsString('<p>', $content);
        $this->assertStringNotContainsString('<strong>', $content);
        $this->assertStringContainsString('Test content with links', $content);
    }

    /**
     * @return array<array{0: string}>
     */
    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }
}
