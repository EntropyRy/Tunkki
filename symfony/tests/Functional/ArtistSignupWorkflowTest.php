<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Entity\User;
use App\Factory\ArtistFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use App\Time\ClockInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Artist Signup workflow tests (factory-based, no legacy fixtures).
 *
 * Refactor Goals:
 *  - Remove dependency on preloaded fixture events (create all via EventFactory).
 *  - Eliminate ensureOpenEntityManager usage (transactional isolation + factories suffice).
 *  - Provide clear, semantic factory state usage for signup window scenarios.
 *  - Cache a single user+artist to avoid redundant one-to-one creation cost.
 */
final class ArtistSignupWorkflowTest extends FixturesWebTestCase
{
    use LoginHelperTrait;
    // (Removed explicit $client property; rely on FixturesWebTestCase magic accessor)
    private RouterInterface $router;
    private EventTemporalStateService $temporalState;
    private ClockInterface $clock;
    private ?string $artistLoginEmail = null;

    protected function setUp(): void
    {
        parent::setUp(); // Base now auto-initializes site-aware client & CMS baseline
        $this->router = static::getContainer()->get(RouterInterface::class);
        $this->temporalState = static::getContainer()->get(
            EventTemporalStateService::class,
        );
        $this->clock = static::getContainer()->get(ClockInterface::class);
    }

    /**
     * Create (once) and log in a user whose Member has an Artist.
     * Uses a consistent email to enable loginAsActiveMember caching.
     */
    private function loginUserWithArtist(): User
    {
        // Use loginAsActiveMember with a consistent email to enable caching
        if (null === $this->artistLoginEmail) {
            $this->artistLoginEmail = 'artistworkflowtest-'.bin2hex(random_bytes(4)).'@example.test';
        }
        $email = $this->artistLoginEmail;
        [$user, $client] = $this->loginAsActiveMember($email);

        // Ensure the user has an artist
        $member = $user->getMember();
        self::assertNotNull($member, 'User must have an associated Member.');

        // Member::getArtist() returns a Collection; check count to determine presence
        $hasArtist = false;
        if (
            method_exists($member, 'getArtist')
            && $member->getArtist() instanceof \Doctrine\Common\Collections\Collection
        ) {
            $hasArtist = $member->getArtist()->count() > 0;
        }
        if (!$hasArtist) {
            ArtistFactory::new()->withMember($member)->dj()->create();
        }

        // Defensive assertion: when this helper returns, the user MUST have an artist.
        if (
            method_exists($member, 'getArtist')
            && $member->getArtist() instanceof \Doctrine\Common\Collections\Collection
        ) {
            self::assertGreaterThan(
                0,
                $member->getArtist()->count(),
                'Expected logged-in member to have at least one Artist.',
            );
        }

        return $user;
    }

    private function generateSignupPath(Event $event, string $locale): string
    {
        $route = 'entropy_event_slug_artist_signup';

        return $this->router->generate($route, [
            '_locale' => $locale,
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);
    }

    public function testEnglishSignupAccessibleWithArtist(): void
    {
        $user = $this->loginUserWithArtist();
        $this->seedClientHome('en');

        $event = EventFactory::new()
            ->signupEnabled()
            ->published()
            ->create([
                // Make slug unique per test run to avoid duplicate key collisions
                'url' => 'artist-signup-event-'.uniqid('', true),
                'name' => 'Artist Signup Event',
            ]);

        self::assertTrue(
            $this->temporalState->isSignupOpen($event),
            'Signup window should be open for enabled state.',
        );

        $pathEn = $this->generateSignupPath($event, 'en');
        self::assertStringStartsWith(
            '/en/',
            $pathEn,
            'English path should start with /en/.',
        );

        $this->client->request('GET', $pathEn);

        // Tighten: a logged-in user with an artist should not be redirected to /login.
        $this->assertResponseStatusCodeSame(200);
        $this->client->assertSelectorTextContains(
            'h1.event-heading a',
            'Artist Signup Event',
            'Expected event name to appear in the English signup page heading.',
        );
    }

    public function testFinnishSignupAccessibleAndEnglishWithoutPrefixFails(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('fi');

        $event = EventFactory::new()
            ->signupEnabled()
            ->published()
            ->create([
                'url' => 'artist-signup-event-'.uniqid('', true),
            ]);

        $pathFi = $this->generateSignupPath($event, 'fi');
        self::assertFalse(
            str_starts_with($pathFi, '/en/'),
            'Finnish path must not start with /en/.',
        );

        $this->client->request('GET', $pathFi);
        $statusFi = $this->client->getResponse()->getStatusCode();
        self::assertTrue(
            \in_array($statusFi, [200, 302, 303], true),
            'Finnish signup page should return 200 or redirect (got '.
                $statusFi.
                ').',
        );

        // Simulate incorrect English path without prefix should 404
        $pathEn = $this->generateSignupPath($event, 'en');
        $unprefixedEn = preg_replace('#^/en#', '', $pathEn);
        $this->client->request('GET', $unprefixedEn);
        self::assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'Unprefixed EN path should 404.',
        );
    }

    public function testSignupRequiresArtistOrRedirectsToCreate(): void
    {
        // This test is specifically about the "no artist profile" branch.
        $member = MemberFactory::new()->active()->create([
            'emailVerified' => true,
        ]);
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $event = EventFactory::new()
            ->signupEnabled()
            ->create(['url' => 'artist-signup-event-'.uniqid('', true)]);

        $pathEn = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $pathEn);

        $this->assertResponseStatusCodeSame(302);
        $loc = $this->client->getResponse()->headers->get('Location') ?? '';
        $redirectPath = parse_url($loc, \PHP_URL_PATH) ?: $loc;

        // Tighten: must redirect to artist create page (not login, not elsewhere)
        self::assertSame('/en/profile/artist/create', $redirectPath);
    }

    public function testSignupRedirectsToCreateAndReturnsAfterArtistCreation(): void
    {
        $member = MemberFactory::new()->active()->create([
            'emailVerified' => true,
        ]);
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $event = EventFactory::new()
            ->signupEnabled()
            ->published()
            ->create([
                'url' => 'artist-signup-event-'.uniqid('', true),
            ]);

        $signupPath = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $signupPath);

        $this->assertResponseStatusCodeSame(302);
        $createLocation = $this->client->getResponse()->headers->get('Location') ?? '';
        $createPath = parse_url($createLocation, \PHP_URL_PATH) ?: $createLocation;
        $this->assertSame('/en/profile/artist/create', $createPath);

        $crawler = $this->client->request('GET', $createPath);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $form['artist[name]'] = 'Signup Flow Artist';
        $form['artist[type]'] = 'DJ';
        $form['artist[hardware]'] = 'CDJs';
        $form['artist[genre]'] = 'Techno';
        $form['artist[bio]'] = 'Bio';
        $form['artist[bioEn]'] = 'Bio EN';

        $imagePath = $this->createTempPng();
        $form['artist[Picture][binaryContent]']->upload($imagePath);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(302);
        $redirect = $this->client->getResponse()->headers->get('Location') ?? '';
        $redirectPath = parse_url($redirect, \PHP_URL_PATH) ?: $redirect;
        $this->assertSame($signupPath, $redirectPath);

        $this->client->request('GET', $redirectPath);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="event_artist_info"]');
    }

    public function testSignupBlockedWhenEventNotEnabled(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('fi');

        $regularEvent = EventFactory::new()
            ->published() // published but signup disabled by default (artistSignUpEnabled=false)
            ->create(['url' => 'artist-signup-disabled-'.uniqid('', true)]);

        self::assertFalse($regularEvent->getArtistSignUpEnabled());

        $pathFi = $this->generateSignupPath($regularEvent, 'fi');
        $this->client->request('GET', $pathFi);
        $status = $this->client->getResponse()->getStatusCode();

        self::assertTrue(
            \in_array($status, [302, 404], true),
            "Expected 302 or 404, got {$status}",
        );
    }

    public function testEnglishAndFinnishPathsDiffer(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('en');

        $event = EventFactory::new()
            ->signupEnabled()
            ->create(['url' => 'artist-signup-event-'.uniqid('', true)]);

        $en = $this->generateSignupPath($event, 'en');
        $fi = $this->generateSignupPath($event, 'fi');

        self::assertNotSame($en, $fi);
        self::assertStringStartsWith('/en/', $en);
        self::assertFalse(str_starts_with($fi, '/en/'));
    }

    public function testSignupWindowNotYetOpenBlocked(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('en');

        $now = $this->clock->now();
        $event = $this->createEventWithSignupWindow(
            $now->modify('+10 minutes'),
            $now->modify('+2 days'),
        );

        self::assertFalse(
            $this->temporalState->isSignupOpen($event),
            'Precondition failed: signup window should not be open yet.',
        );

        $pathEn = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $pathEn);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403, 404],
            "Expected denial codes for not-yet-open window, got {$status}",
        );

        if (302 === $status) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            self::assertDoesNotMatchRegularExpression('#/artist-signup(/|$)#', $loc);
        }
    }

    public function testSignupWindowEndedBlocked(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('fi');

        $now = $this->clock->now();
        $event = $this->createEventWithSignupWindow(
            $now->modify('-3 days'),
            $now->modify('-10 minutes'),
        );

        $pathFi = $this->generateSignupPath($event, 'fi');
        $this->client->request('GET', $pathFi);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403, 404],
            "Expected denial codes for ended window, got {$status}",
        );
    }

    public function testPastEventSignupWindowOpenStillBlocked(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('en');

        $now = $this->clock->now();
        $event = $this->createEventWithSignupWindow(
            $now->modify('-14 days'),
            $now->modify('+2 days'),
            [
                'eventDate' => $now->modify('-1 day'),
                'publishDate' => $now->modify('-30 days'),
            ],
        );

        $pathEn = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $pathEn);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403, 404],
            "Expected denial codes for past event signup, got {$status}",
        );
    }

    private function createEventWithSignupWindow(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $overrides = [],
    ): Event {
        $baseNow = $this->clock->now();

        return EventFactory::new()->create(
            array_merge(
                [
                    'published' => true,
                    'publishDate' => $baseNow->modify('-5 minutes'),
                    'artistSignUpEnabled' => true,
                    'artistSignUpStart' => $start,
                    'artistSignUpEnd' => $end,
                    'eventDate' => $baseNow->modify('+14 days'),
                    'url' => 'artist-signup-event-'.uniqid('', true),
                ],
                $overrides,
            ),
        );
    }

    private function createTempPng(): string
    {
        $path = __DIR__.'/../../assets/images/golden-logo.png';
        if (!is_file($path)) {
            self::fail('Expected fixture image not found for upload.');
        }

        return $path;
    }
}
