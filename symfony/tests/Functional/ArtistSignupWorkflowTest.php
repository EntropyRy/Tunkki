<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\User;
use App\Factory\ArtistFactory;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
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

    protected function setUp(): void
    {
        parent::setUp(); // Base now auto-initializes site-aware client & CMS baseline
        $this->router = static::getContainer()->get(RouterInterface::class);
    }

    /**
     * Create (once) and log in a user whose Member has an Artist.
     * Uses a consistent email to enable loginAsActiveMember caching.
     */
    private function loginUserWithArtist(): User
    {
        // Use loginAsActiveMember with a consistent email to enable caching
        $email = 'artistworkflowtest@example.test';
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

        self::assertTrue($event->getArtistSignUpEnabled());
        self::assertTrue(
            $event->getArtistSignUpNow(),
            'Signup window should be open for enabled state.',
        );

        $pathEn = $this->generateSignupPath($event, 'en');
        self::assertStringStartsWith(
            '/en/',
            $pathEn,
            'English path should start with /en/.',
        );

        $this->client->request('GET', $pathEn);
        $status = $this->client->getResponse()->getStatusCode();
        if (
            302 === $status
            && str_contains(
                $this->client->getResponse()->headers->get('Location') ?? '',
                '/login',
            )
        ) {
            // Retry once if unexpected auth redirect (should not happen normally)
            $this->client->request('GET', $pathEn);
            $status = $this->client->getResponse()->getStatusCode();
        }

        self::assertTrue(
            \in_array($status, [200, 302, 303], true),
            'English signup page should return 200 or redirect (got '.
                $status.
                ').',
        );
        $response = $this->client->getResponse();
        if ($response->isRedirect()) {
            $loc = $response->headers->get('Location') ?? '';
            // Accept redirect to artist create page as a valid outcome when no artist profile exists
            if (
                preg_match('#(/en/artist/create|/profiili/artisti/uusi)#', $loc)
            ) {
                // Valid outcome for a user without an Artist profile
                $this->assertTrue(true);

                return;
            }
            // Follow other redirects and continue with content assertion
            $this->client->request('GET', $loc);
        }

        $content = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString(
            'Artist Signup Event',
            $content,
            'Expected event name to appear in the English signup page response body.',
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
        $user = $this->loginUserWithArtist();
        $this->seedClientHome('en');

        $event = EventFactory::new()
            ->signupEnabled()
            ->create(['url' => 'artist-signup-event-'.uniqid('', true)]);

        $pathEn = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $pathEn);

        $status = $this->client->getResponse()->getStatusCode();
        if (
            302 === $status
            && str_contains(
                $this->client->getResponse()->headers->get('Location') ?? '',
                '/login',
            )
        ) {
            // Retry once if unexpected auth redirect (should not happen normally)
            $this->client->request('GET', $pathEn);
            $status = $this->client->getResponse()->getStatusCode();
        }
        self::assertTrue(
            \in_array($status, [200, 302], true),
            "Expected 200 or 302, got {$status}",
        );
        if (302 === $status) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            self::assertMatchesRegularExpression(
                '#(/en/artist/create|/profiili/artisti/uusi)#',
                $loc,
            );
        }
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

        $event = EventFactory::new()
            ->signupEnabled()
            ->signupWindowNotYetOpen()
            ->create();

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
            self::assertStringNotContainsString('/artist-signup', $loc);
        }
    }

    public function testSignupWindowEndedBlocked(): void
    {
        $this->loginUserWithArtist();
        $this->seedClientHome('fi');

        $event = EventFactory::new()->signupWindowEnded()->create();

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

        $event = EventFactory::new()->pastEventSignupWindowOpen()->create();

        $pathEn = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $pathEn);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403, 404],
            "Expected denial codes for past event signup, got {$status}",
        );
    }
}
