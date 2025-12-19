<?php

declare(strict_types=1);

namespace App\Tests\Functional\Event;

use App\Entity\RSVP;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\RSVPFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

final class RSVPRouteTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        // This test exercises localized POST routes. With the site-aware router, per-locale
        // matchers may cache RequestContext from the first request (GET). Rebooting the kernel
        // between requests ensures the router context is fresh for POST matching.
        $this->client->enableReboot();
    }

    #[DataProvider('localeProvider')]
    public function testLoggedInMemberCanRsvpViaPostRoute(string $locale): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $year,
            'slug' => $slug,
        ]);
        $this->client->request('POST', $path);

        $this->assertResponseStatusCodeSame(302);

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        self::assertCount(1, $rsvps);
    }

    #[DataProvider('localeProvider')]
    public function testLoggedInMemberDuplicateRsvpDoesNotCreateSecondRow(string $locale): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $year,
            'slug' => $slug,
        ]);

        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);

        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        self::assertCount(1, $rsvps);
    }

    #[DataProvider('localeProvider')]
    public function testLoggedInMemberWhenRsvpDisabledShowsWarningFlash(string $locale): void
    {
        $event = EventFactory::new()->create(); // RSVP disabled by default

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $year,
            'slug' => $slug,
        ]);
        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-warning');
        $this->client->assertSelectorTextContains('.alert.alert-warning', $this->expectedRsvpDisabledMessage($locale));
    }

    #[DataProvider('localeProvider')]
    public function testLoggedInMemberAlreadyRsvpdShowsWarningFlash(string $locale): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        RSVPFactory::new()
            ->forEvent($event)
            ->create([
                'member' => $member,
                'firstName' => null,
                'lastName' => null,
                'email' => null,
            ]);

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $year,
            'slug' => $slug,
        ]);
        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-warning');
        $this->client->assertSelectorTextContains('.alert.alert-warning', $this->expectedAlreadyRsvpdMessage($locale));

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        self::assertCount(1, $rsvps);
    }

    #[DataProvider('localeProvider')]
    public function testAnonymousIsRedirectedToLogin(string $locale): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $this->seedClientHome($locale);
        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $year,
            'slug' => $slug,
        ]);
        $this->client->request('POST', $path);

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $path = parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH);
        self::assertContains($path, ['/login', '/en/login']);
    }

    public static function localeProvider(): array
    {
        return [['fi'], ['en']];
    }

    private function expectedRsvpDisabledMessage(string $locale): string
    {
        return 'en' === $locale
            ? 'RSVP is not enabled for this event.'
            : 'Ilmoittautuminen ei ole käytössä tässä tapahtumassa.';
    }

    private function expectedAlreadyRsvpdMessage(string $locale): string
    {
        return 'en' === $locale
            ? 'You have already RSVPed to this event.'
            : 'Olet jo ilmoittautunut tähän tapahtumaan.';
    }
}
