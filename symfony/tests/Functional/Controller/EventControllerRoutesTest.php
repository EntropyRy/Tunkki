<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\EventArtistInfo;
use App\Entity\Location;
use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\TicketFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Test\Factories;

final class EventControllerRoutesTest extends FixturesWebTestCase
{
    use Factories;
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    #[DataProvider('localeProvider')]
    public function testEventSlugPageRendersForPublishedEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-routes-'.uniqid('', true),
            'name' => 'English Event Name',
            'nimi' => 'Suomenkielinen Tapahtuma',
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_slug', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $expectedName = 'en' === $locale ? 'English Event Name' : 'Suomenkielinen Tapahtuma';
        $this->client->assertSelectorExists('h1.event-name');
        $this->client->assertSelectorTextContains('h1.event-name', $expectedName);
    }

    #[DataProvider('localeProvider')]
    public function testOneSlugShowsTicketsLinkWhenUserHasTicket(string $locale): void
    {
        $event = EventFactory::new()
            ->published()
            ->ticketed()
            ->create([
                'url' => 'event-tickets-link-'.uniqid('', true),
                // Ensure the ticket token is actually rendered on the event page.
                'Content' => '{{ stripe_ticket }}',
                'Sisallys' => '{{ stripe_ticket }}',
            ]);

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        TicketFactory::new()
            ->forEvent($event)
            ->ownedBy($member)
            ->reserved()
            ->create();

        $year = $event->getEventDate()->format('Y');
        $slug = $event->getUrl();

        $eventPath = static::getContainer()->get('router')->generate('entropy_event_slug', [
            'year' => $year,
            'slug' => $slug,
            '_locale' => $locale,
        ]);
        $ticketsPath = static::getContainer()->get('router')->generate('entropy_event_tickets', [
            'year' => $year,
            'slug' => $slug,
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $eventPath);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('#ticket a.btn.btn-primary.w-100');
        $this->client->assertSelectorExists(\sprintf('#ticket a.btn.btn-primary.w-100[href="%s"]', $ticketsPath));
    }

    #[DataProvider('localeProvider')]
    public function testEventArtistsRendersForPublishedEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-artists-'.uniqid('', true),
            'name' => 'English Event Name',
            'nimi' => 'Suomenkielinen Tapahtuma',
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_artists', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $expectedName = 'en' === $locale ? 'English Event Name' : 'Suomenkielinen Tapahtuma';
        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', $this->trans('Artists', $locale));
        $this->client->assertSelectorExists('h1 a');
        $this->client->assertSelectorTextContains('h1 a', $expectedName);
    }

    #[DataProvider('localeProvider')]
    public function testEventTimetableRendersForPublishedEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-timetable-'.uniqid('', true),
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_timetable', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('div.content');
    }

    #[DataProvider('localeProvider')]
    public function testEventInfoRendersForPublishedEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-info-'.uniqid('', true),
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_info', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('div.content');
    }

    #[DataProvider('localeProvider')]
    public function testEventSaferSpaceRendersForPublishedEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-safer-'.uniqid('', true),
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_safer_space', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('div.content');
    }

    #[DataProvider('localeProvider')]
    public function testEventLocationRendersNotFoundWhenCoordinatesMissing(string $locale): void
    {
        $location = (new Location())
            ->setName('Entropy Clubroom')
            ->setNameEn('Entropy Clubroom')
            ->setStreetAddress('Street 1, City');

        $event = EventFactory::new()->published()->create([
            'url' => 'event-location-missing-coords-'.uniqid('', true),
            'location' => $location,
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_location', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', $this->trans('event.location.title', $locale));
        $this->client->assertSelectorTextContains('p', $this->trans('event.location.not_found', $locale));
    }

    #[DataProvider('localeProvider')]
    public function testEventLocationIsDeniedWhenLocationIsMissing(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-location-missing-'.uniqid('', true),
            'location' => null,
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_location', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $path = parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH);
        self::assertContains($path, ['/login', '/en/login']);
    }

    #[DataProvider('routeProvider')]
    public function testAnonymousCannotAccessUnpublishedEventRoutes(
        string $routeName,
        string $locale,
    ): void {
        $event = EventFactory::new()->unpublished()->create([
            'url' => 'event-unpublished-'.uniqid('', true),
        ]);

        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate($routeName, [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $path = parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH);
        self::assertContains($path, ['/login', '/en/login']);
    }

    #[DataProvider('localeProvider')]
    public function testUnknownSlugReturns404(string $locale): void
    {
        $this->seedClientHome($locale);

        $path = static::getContainer()->get('router')->generate('entropy_event_slug', [
            'year' => '2099',
            'slug' => 'this-does-not-exist',
            '_locale' => $locale,
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseStatusCodeSame(404);
    }

    public static function localeProvider(): array
    {
        return [['fi'], ['en']];
    }

    public static function routeProvider(): array
    {
        return [
            ['entropy_event_slug', 'fi'],
            ['entropy_event_slug', 'en'],
            ['entropy_event_artists', 'fi'],
            ['entropy_event_artists', 'en'],
            ['entropy_event_timetable', 'fi'],
            ['entropy_event_timetable', 'en'],
            ['entropy_event_info', 'fi'],
            ['entropy_event_info', 'en'],
            ['entropy_event_safer_space', 'fi'],
            ['entropy_event_safer_space', 'en'],
        ];
    }

    /**
     * Tests EventArtistInfo::timediff() via timetable rendering.
     * Gap chip appears when multiday event has >4h gap between slots.
     */
    public function testTimetableShowsGapChipForMultidayEventWithLargeGap(): void
    {
        $event = EventFactory::new()->published()->multiday()->create([
            'url' => 'event-timetable-gap-'.uniqid('', true),
        ]);

        $eventDate = $event->getEventDate();

        $artist1 = ArtistFactory::new()->create(['type' => 'DJ', 'name' => 'First DJ']);
        $artist2 = ArtistFactory::new()->create(['type' => 'DJ', 'name' => 'Second DJ']);

        // First slot at 20:00
        EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist1)
            ->create([
                'StartTime' => $eventDate->setTime(20, 0),
                'stage' => 'Main',
            ]);

        // Second slot at 02:00 next day (6 hours gap)
        EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist2)
            ->create([
                'StartTime' => $eventDate->modify('+1 day')->setTime(2, 0),
                'stage' => 'Main',
            ]);

        $this->em()->clear();
        $this->seedClientHome('fi');

        $path = static::getContainer()->get('router')->generate('entropy_event_timetable', [
            'year' => $eventDate->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => 'fi',
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.gap-chip');
    }

    /**
     * Tests timetable renders artist names from artistClone.
     */
    public function testTimetableRendersArtistNamesFromClone(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-timetable-artist-'.uniqid('', true),
        ]);

        $artist = ArtistFactory::new()->create([
            'type' => 'DJ',
            'name' => 'Test Artist Name',
        ]);

        EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create([
                'StartTime' => $event->getEventDate()->setTime(22, 0),
                'stage' => 'Main',
            ]);

        $this->em()->clear();
        $this->seedClientHome('fi');

        $path = static::getContainer()->get('router')->generate('entropy_event_timetable', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
            '_locale' => 'fi',
        ]);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorTextContains('.timetable', 'Test Artist Name');
    }

    /**
     * Tests EventArtistInfo::__toString() returns artist name.
     */
    public function testEventArtistInfoToStringReturnsArtistName(): void
    {
        $artist = new \App\Entity\Artist();
        $artist->setName('Stringable Artist');
        $artist->setType('DJ');

        $info = new EventArtistInfo();
        $info->setArtist($artist);

        $this->assertSame('Stringable Artist', (string) $info);
    }

    /**
     * Tests EventArtistInfo::__toString() returns 'n/a' when no artist.
     */
    public function testEventArtistInfoToStringReturnsNaWhenNoArtist(): void
    {
        $info = new EventArtistInfo();

        $this->assertSame('n/a', (string) $info);
    }

    private function trans(string $key, string $locale): string
    {
        $translator = static::getContainer()->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return $translator->trans($key, [], null, $locale);
    }
}
