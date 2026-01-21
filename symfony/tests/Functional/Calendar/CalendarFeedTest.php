<?php

declare(strict_types=1);

namespace App\Tests\Functional\Calendar;

use App\Entity\Location;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Sqids\Sqids;

/**
 * Functional coverage for CalendarController::eventCalendar().
 *
 * These tests exercise the ICS feed end-to-end, including hash decoding,
 * event type filtering, bilingual routes, reminder generation, and
 * hybrid location rendering (physical + online).
 */
final class CalendarFeedTest extends FixturesWebTestCase
{
    private const EVENT_TITLES = [
        'event' => [
            'fi' => 'Kalenteri-ilta',
            'en' => 'Calendar Night',
        ],
        'meeting' => [
            'fi' => 'Hallituksen palaveri',
            'en' => 'Board Meeting',
        ],
        'clubroom' => [
            'fi' => 'Klubihuone-ilta',
            'en' => 'Clubroom Session',
        ],
    ];

    #[DataProvider('localeProvider')]
    public function testCalendarFeedIncludesOnlyEnabledEventTypes(
        string $locale,
        string $pathPattern,
    ): void {
        EventFactory::new()
            ->with([
                'type' => 'event',
                'name' => self::EVENT_TITLES['event']['en'],
                'nimi' => self::EVENT_TITLES['event']['fi'],
            ])
            ->create();

        EventFactory::new()
            ->with([
                'type' => 'clubroom',
                'name' => self::EVENT_TITLES['clubroom']['en'],
                'nimi' => self::EVENT_TITLES['clubroom']['fi'],
            ])
            ->create();

        $meeting = EventFactory::new()
            ->with([
                'type' => 'meeting',
                'name' => self::EVENT_TITLES['meeting']['en'],
                'nimi' => self::EVENT_TITLES['meeting']['fi'],
            ])
            ->create();

        $hash = $this->encodeHash([1, 0, 0, 0, 1, 0]);
        $path = \sprintf($pathPattern, $hash);

        $this->seedClientHome($locale);
        $this->client->request('GET', $path);

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            'text/calendar; charset=utf-8',
            $response->headers->get('Content-Type'),
        );

        $body = $response->getContent();
        $this->assertNotFalse($body);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $expectedEventSummary = self::EVENT_TITLES['event'][$locale];
        $expectedMeetingSummary = self::EVENT_TITLES['meeting'][$locale];
        $blockedClubSummary = self::EVENT_TITLES['clubroom'][$locale];

        $this->assertStringContainsString(
            'SUMMARY:'.$expectedEventSummary,
            $body,
            'Enabled events should be present in the ICS feed.',
        );
        $this->assertStringContainsString(
            'SUMMARY:'.$expectedMeetingSummary,
            $body,
            'Enabled meetings should be present in the ICS feed.',
        );
        $this->assertStringNotContainsString(
            'SUMMARY:'.$blockedClubSummary,
            $body,
            'Disabled clubroom events must be filtered out.',
        );
        $this->assertStringContainsString(
            'URL:'.$meeting->getUrlByLang($locale),
            $body,
            'Absolute event URLs should be rendered for enabled entries.',
        );
    }

    public function testCalendarFeedIncludesReminderAndHybridLocation(): void
    {
        $location = new Location();
        $location
            ->setName('Entropy Klubi')
            ->setNameEn('Entropy Club')
            ->setStreetAddress('Testikatu 1, Helsinki');
        $this->em()->persist($location);
        $this->em()->flush();

        EventFactory::new()->create([
            'type' => 'event',
            'name' => 'Hybrid Event',
            'nimi' => 'Hybridi Tapahtuma',
            'webMeetingUrl' => 'https://meet.example.com/entropy',
            'location' => $location,
        ]);

        $hash = $this->encodeHash([1, 1, 0, 0, 0, 0]);
        $path = \sprintf('/%s/kalenteri.ics', $hash);

        $this->seedClientHome('fi');
        $this->client->request('GET', $path);

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            'attachment; filename="entropy.ics"',
            $response->headers->get('Content-Disposition'),
        );

        $body = $response->getContent();
        $this->assertNotFalse($body);

        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString(
            'BEGIN:VALARM',
            $body,
            'Notifications enabled should emit VALARM blocks.',
        );
        $this->assertStringContainsString(
            'Muistutus huomisesta Entropy tapahtumasta!',
            $body,
        );
        $normalizedBody = str_replace("\r\n ", '', $body);
        $this->assertStringContainsString(
            'Entropy Klubi – Testikatu 1\, Helsinki (Online: https://meet.example.com/entropy)',
            $normalizedBody,
            'Hybrid events should include combined physical + online location info.',
        );
    }

    public static function localeProvider(): array
    {
        return [
            ['fi', '/%s/kalenteri.ics'],
            ['en', '/en/%s/calendar.ics'],
        ];
    }

    /**
     * Encode the calendar configuration flags into a Sqids hash.
     *
     * Expected order matches CalendarConfigType checkboxes:
     *  [events, events_notifications, clubroom, clubroom_notifications, meetings, meetings_notifications]
     */
    private function encodeHash(array $flags): string
    {
        if (6 !== \count($flags)) {
            throw new \InvalidArgumentException('Six configuration flags expected.');
        }

        $sqids = new Sqids();

        return $sqids->encode([...$flags, 123]);
    }
}
