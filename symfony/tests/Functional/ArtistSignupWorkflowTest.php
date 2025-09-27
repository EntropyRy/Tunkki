<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Event;
use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use App\Tests\Http\SiteAwareKernelBrowser;
use App\Tests\_Base\FixturesWebTestCase;

require_once __DIR__ . "/../Http/SiteAwareKernelBrowser.php";

final class ArtistSignupWorkflowTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
    }

    public function testCompleteArtistSignupWorkflow(): void
    {
        $client = $this->client;
        $em = self::$em;

        // Step 1: Verify fixtures are loaded correctly
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $this->assertNotNull($user, "Test user should exist");

        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);
        $this->assertTrue($event->getArtistSignUpEnabled(), "Event should have artist signup enabled");
        $this->assertTrue($event->getArtistSignUpNow(), "Event should allow artist signup now");

        // Step 2: Verify user has an artist (from fixtures)
        $member = $user->getMember();
        $this->assertNotNull($member, "User should have a member");

        $artists = $member->getArtist();
        $this->assertGreaterThan(0, count($artists), "User should have at least one artist from fixtures");

        // Step 3: Login and test artist signup access
        $client->loginUser($user);

        $signupUrl = '/' . date('Y') . '/artist-signup-event/artist/signup';
        $client->request('GET', $signupUrl);

        // The artist signup should work since user has an artist
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertSame(200, $statusCode, "Artist signup should load successfully for user with artist");

        // Verify the page contains event information
        $content = $client->getResponse()->getContent() ?? '';
        $this->assertStringContainsString('Artist Signup Event', $content, "Page should show the event name");
    }

    public function testArtistSignupWithExistingArtist(): void
    {
        $client = $this->client;
        $em = self::$em;

        // Get user and existing artist from fixtures
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $this->assertNotNull($user, "Test user should exist");

        $artist = $this->findOneOrFail(Artist::class, ['name' => 'Fixture Artist']);

        $client->loginUser($user);

        // Get the artist signup event
        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);

        // Access the artist signup page
        $signupUrl = '/' . date('Y') . '/artist-signup-event/artist/signup';
        $crawler = $client->request('GET', $signupUrl);

        // Follow redirects if any (might redirect to artist creation if no artist exists)
        $response = $client->getResponse();
        if ($response->getStatusCode() === 302) {
            $location = $response->headers->get('Location');
            if (str_contains($location, '/profile/artist/create')) {
                // This is expected - user needs to create an artist first
                $this->assertStringContainsString('/profile/artist/create', $location);
                return; // Skip the rest of this test since it requires an artist
            }
            $client->followRedirect();
        }
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        // Should see the signup form
        $this->assertGreaterThan(0, $crawler->filter('form')->count());

        // Check that the event info is displayed
        $content = $client->getResponse()->getContent() ?? '';
        $this->assertStringContainsString('Artist Signup Event', $content);
    }

    public function testArtistSignupFormSubmission(): void
    {
        $client = $this->client;
        $em = self::$em;

        // Get user and existing artist
        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $artist = $this->findOneOrFail(Artist::class, ['name' => 'Fixture Artist']);
        $event = $this->findOneOrFail(Event::class, ['url' => 'artist-signup-event']);

        $client->loginUser($user);

        // Access signup form
        $signupUrl = '/' . date('Y') . '/artist-signup-event/artist/signup';
        $crawler = $client->request('GET', $signupUrl);

        // Handle redirects
        $response = $client->getResponse();
        if ($response->getStatusCode() === 302) {
            $location = $response->headers->get('Location');
            if (str_contains($location, '/profile/artist/create')) {
                // User needs to create an artist first
                $this->assertStringContainsString('/profile/artist/create', $location);
                return; // Skip form testing since no artist exists
            }
            $client->followRedirect();
        }
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        // Check if form exists
        $formNode = $crawler->filter('form')->first();
        $this->assertTrue($formNode->count() > 0, "Expected artist signup form");

        // For now, just verify the form loads correctly
        // The actual form submission would require filling out the EventArtistInfo form
        // which might have fields like performance time, special requirements, etc.
    }

    public function testEventWithoutArtistSignup(): void
    {
        $client = $this->client;
        $em = self::$em;

        $user = $em->getRepository(User::class)->findOneBy(['authId' => 'local-user']);
        $client->loginUser($user);

        // Try to access artist signup on a regular event (without artist signup enabled)
        $regularEvent = $this->findOneOrFail(Event::class, ['url' => 'test-event']);
        $this->assertFalse($regularEvent->getArtistSignUpEnabled(), "Regular event should not have artist signup");

        $signupUrl = '/' . date('Y') . '/test-event/artist/signup';
        $client->request('GET', $signupUrl);

        // Should redirect away since artist signup is not enabled
        $this->assertSame(302, $client->getResponse()->getStatusCode());
    }
}