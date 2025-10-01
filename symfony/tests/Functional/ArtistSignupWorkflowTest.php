<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;
use Symfony\Component\Routing\RouterInterface;

/**
 * Updated Artist Signup workflow tests aligned with structural locale routing:
 *  - Use router to generate localized route names (entropy_event_slug_artist_signup.en / .fi)
 *  - English paths always include /en prefix; Finnish never has it.
 *  - Avoid hardcoded path concatenation that ignored locale.
 */
final class ArtistSignupWorkflowTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;
    private RouterInterface $router;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->router = static::getContainer()->get(RouterInterface::class);
        $this->client = new SiteAwareKernelBrowser($kernel);
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    private function generateSignupPath(Event $event, string $locale): string
    {
        // Actual concrete route names are suffixed: entropy_event_slug_artist_signup.en / .fi
        $route = 'entropy_event_slug_artist_signup.'.$locale;

        return $this->router->generate($route, [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);
    }

    public function testEnglishSignupAccessibleWithArtist(): void
    {
        $em = self::$em;
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $this->assertNotNull($user, 'Fixture user missing.');
        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);
        $this->assertTrue($event->getArtistSignUpEnabled(), 'Signup must be enabled.');
        $this->assertTrue($event->getArtistSignUpNow(), 'Signup window must be open.');

        $member = $user->getMember();
        $this->assertNotNull($member, 'User must have member.');
        $this->assertGreaterThan(0, \count($member->getArtist()), 'User must have at least one artist.');

        $this->client->loginUser($user);

        $pathEn = $this->generateSignupPath($event, 'en');
        // Structural: English path already includes /en prefix.
        $this->assertStringStartsWith('/en/', $pathEn, 'English path should start with /en/');

        $this->client->request('GET', $pathEn);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'English signup page should load (200).');

        $html = $this->client->getResponse()->getContent() ?? '';
        $this->assertStringContainsString('Artist Signup Event', $html, 'Event name should appear on EN page.');
    }

    public function testFinnishSignupAccessibleAndEnglishWithoutPrefixFails(): void
    {
        $em = self::$em;
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $this->client->loginUser($user);

        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);

        $pathFi = $this->generateSignupPath($event, 'fi');
        $this->assertStringStartsWith('/', $pathFi);
        $this->assertFalse(str_starts_with($pathFi, '/en/'), 'Finnish path must NOT start with /en/.');

        // Finnish canonical path should 200
        $this->client->request('GET', $pathFi);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Finnish signup page should load (200).');

        // Wrong-locale attempt: remove /en from EN path (simulate old style) should 404 now
        $pathEn = $this->generateSignupPath($event, 'en');
        $unprefixedEn = preg_replace('#^/en#', '', $pathEn);
        $this->client->request('GET', $unprefixedEn);
        $this->assertSame(404, $this->client->getResponse()->getStatusCode(), 'Unprefixed EN path must 404 structurally.');
    }

    public function testSignupRequiresArtistOrRedirectsToCreate(): void
    {
        $em = self::$em;
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $this->assertNotNull($user);
        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);

        $this->client->loginUser($user);

        $pathEn = $this->generateSignupPath($event, 'en');
        $this->client->request('GET', $pathEn);

        $status = $this->client->getResponse()->getStatusCode();
        // Two acceptable outcomes:
        //  - 200: user already has artist (normal flow)
        //  - 302: redirect to artist creation if fixture setup changes
        $this->assertTrue(
            \in_array($status, [200, 302], true),
            "Expected 200 or 302 for signup access, got {$status}"
        );
        if (302 === $status) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            $this->assertStringContainsString('/artist/create', $loc, 'Redirect should go to artist creation.');
        }
    }

    public function testSignupBlockedWhenEventNotEnabled(): void
    {
        $em = self::$em;
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $this->client->loginUser($user);

        $regularEvent = $this->findOneOrFail(Event::class, ['url' => 'test-event']);
        $this->assertFalse($regularEvent->getArtistSignUpEnabled(), 'Regular event must not have signup enabled.');

        // Try Finnish path (most permissive)
        $pathFi = $this->router->generate('entropy_event_slug_artist_signup.fi', [
            'year' => $regularEvent->getEventDate()->format('Y'),
            'slug' => $regularEvent->getUrl(),
        ]);

        $this->client->request('GET', $pathFi);
        $status = $this->client->getResponse()->getStatusCode();
        // We expect either a redirect to profile/dashboard OR structural 404 depending on controller guard
        $this->assertTrue(
            \in_array($status, [302, 404], true),
            "Expected 302 redirect or 404 for disabled signup, got {$status}"
        );
    }

    public function testEnglishAndFinnishPathsDiffer(): void
    {
        $em = self::$em;
        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);

        $en = $this->generateSignupPath($event, 'en');
        $fi = $this->generateSignupPath($event, 'fi');

        $this->assertNotSame($en, $fi, 'EN and FI signup paths must differ.');
        $this->assertStringStartsWith('/en/', $en, 'English path must start with /en/.');
        $this->assertFalse(str_starts_with($fi, '/en/'), 'Finnish path must not start with /en/.');
    }
}
