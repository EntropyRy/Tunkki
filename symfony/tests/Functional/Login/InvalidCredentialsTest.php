<?php

declare(strict_types=1);

namespace App\Tests\Functional\Login;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Negative credential & authentication robustness tests.
 *
 * Roadmap alignment:
 *  - #13 Split oversized LoginTest
 *  - #16/#23 WebTestAssertions + reduced manual DOM parsing
 *  - #20 Negative path coverage (invalid credentials)
 *
 * Assumptions (adjust if your security config differs):
 *  - Login form at /login
 *  - Fields: _username, _password, optional _csrf_token
 *  - On failure: either a 200 (with form & error) or a 302 back to /login
 */
final class InvalidCredentialsTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        // Seed an initial request so BrowserKit assertions have a response/crawler
        $this->seedLoginPage('fi');
    }

    /**
     * Override default client creation to reuse the initialized site-aware client.
     * Avoids secondary kernel boots (LogicException in WebTestCase).
     */
    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client;
    }
    private const VALID_PASSWORD = 'password'; // Matches factory bcrypt hash
    private const INVALID_PASSWORD = 'wrongpass!';

    private function ensureClientReady(): void
    {
        if (
            !self::$client instanceof \Symfony\Bundle\FrameworkBundle\KernelBrowser
        ) {
            $this->initSiteAwareClient();
        }

        if (
            $this->client instanceof \Symfony\Bundle\FrameworkBundle\KernelBrowser
            && self::$client !== $this->client
        ) {
            self::$client = $this->client;
        }

        // setUp() already seeds a request; only seed if no response exists to avoid duplicate requests
        if (null === $this->client->getResponse()) {
            $this->seedLoginPage('fi');
        }
    }

    public function testInvalidPasswordDoesNotAuthenticate(): void
    {
        $this->ensureClientReady();
        $client = $this->client;

        $member = MemberFactory::new()->english()->create();
        $email = $member->getEmail();
        self::assertNotEmpty($email, 'Factory did not yield a member email.');

        $crawler = $client->request('GET', '/login');
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'Login page should return 200.');
        $this->assertGreaterThan(0, $crawler->filter('form')->count(), 'Expected a login form on the page.');

        $csrf = $this->extractCsrfToken($crawler);

        $form = $crawler
            ->filter('form')
            ->first()
            ->form([
                '_username' => $email,
                '_password' => self::INVALID_PASSWORD,
            ]);

        if ($csrf) {
            $form['_csrf_token'] = $csrf;
        }

        $client->submit($form);

        if ($client->getResponse()->isRedirect()) {
            $this->assertContains($client->getResponse()->getStatusCode(), [301, 302, 303]);
            $location = $client->getResponse()->headers->get('Location') ?? '';
            $this->assertStringContainsString('/login', $location);
            $crawler = $client->request('GET', $location);
            $this->assertSame(200, $client->getResponse()->getStatusCode());
            $this->assertGreaterThan(0, $crawler->filter('form input[name="_username"]')->count());
        } else {
            $this->assertSame(
                200,
                $client->getResponse()->getStatusCode(),
                'Invalid password should re-display login form (200) when not redirecting.',
            );
        }

        $this->assertNotAuthenticated(
            'User must not be authenticated after wrong password.',
        );
    }

    public function testUnknownEmailDoesNotAuthenticate(): void
    {
        $this->ensureClientReady();
        $client = $this->client;

        $crawler = $client->request('GET', '/login');
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'Login page should return 200.');
        $csrf = $this->extractCsrfToken($crawler);

        $form = $crawler
            ->filter('form')
            ->first()
            ->form([
                '_username' => 'nonexistent+'.mt_rand(1000, 9999).'@example.invalid',
                '_password' => self::INVALID_PASSWORD,
            ]);

        if ($csrf) {
            $form['_csrf_token'] = $csrf;
        }

        $client->submit($form);

        if ($client->getResponse()->isRedirect()) {
            $this->assertContains($client->getResponse()->getStatusCode(), [301, 302, 303]);
            $location = $client->getResponse()->headers->get('Location') ?? '';
            $this->assertStringContainsString('/login', $location);
            $crawler = $client->request('GET', $location);
            $this->assertSame(200, $client->getResponse()->getStatusCode());
            $this->assertGreaterThan(0, $crawler->filter('form input[name="_username"]')->count());
        } else {
            $this->assertSame(
                200,
                $client->getResponse()->getStatusCode(),
                'Unknown user submission should re-display login form (200) if not redirecting.',
            );
        }

        $this->assertNotAuthenticated(
            'Unknown user should not produce an authenticated session.',
        );
    }

    public function testMissingCsrfTokenRejectedIfCsrfEnabled(): void
    {
        $this->ensureClientReady();
        $client = $this->client;
        $crawler = $client->request('GET', '/login');
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'Login page should return 200.');

        $hasCsrfField =
            $crawler->filter('input[name="_csrf_token"]')->count() > 0;
        // Proceed regardless of CSRF presence (token enforcement only meaningful if field exists)

        // Create a valid user
        $member = MemberFactory::new()->english()->create();
        $email = $member->getEmail();

        // Submit without CSRF intentionally
        $form = $crawler
            ->filter('form')
            ->first()
            ->form([
                '_username' => $email,
                '_password' => self::VALID_PASSWORD,
                '_csrf_token' => 'invalid',
            ]);

        $client->submit($form);

        $status = $client->getResponse()->getStatusCode();
        if ($status >= 500) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            @fwrite(STDERR, "[InvalidCredentialsTest] 5xx on login submission: status={$status} location={$loc}\n");
            $this->fail('Login submission returned server error: '.$status);
        }

        if ($client->getResponse()->isRedirect()) {
            $this->assertContains($status, [301, 302, 303]);
            $location = $client->getResponse()->headers->get('Location') ?? '';
            // Normalize absolute URLs to path for assertions/requests
            $path = parse_url($location, PHP_URL_PATH) ?: $location;
            $this->assertTrue(
                '/' === $path || str_contains($path, '/login') || 1 === preg_match('#^/(en/)?login#', $path),
                'Expected redirect to / or a localized login route, got: '.$location
            );
            $crawler = $client->request('GET', $path);
            $status3 = $client->getResponse()->getStatusCode();
            if ($status3 >= 500) {
                @fwrite(STDERR, "[InvalidCredentialsTest] 5xx on canonical login route: status={$status3}\n");
                $this->fail('Canonical login route returned server error: '.$status3);
            }
            $this->assertSame(200, $status3);
            // If we didn't land on a login page (e.g., redirected to "/"), try canonical login to assert form presence
            if (0 === $crawler->filter('form input[name=\"_username\"]')->count()) {
                $crawler = $client->request('GET', '/login');
                if (in_array($client->getResponse()->getStatusCode(), [301, 302, 303], true)) {
                    $loc2 = $client->getResponse()->headers->get('Location') ?? '';
                    $path2 = parse_url($loc2, PHP_URL_PATH) ?: ($loc2 ?: '/en/login');
                    $crawler = $client->request('GET', $path2);
                }
                $status2 = $client->getResponse()->getStatusCode();
                if ($status2 >= 500) {
                    @fwrite(STDERR, "[InvalidCredentialsTest] 5xx on login redirect target: status={$status2}\n");
                    $this->fail('Login redirect target returned server error: '.$status2);
                }
                $this->assertSame(200, $status2);
            }
            $this->assertTrue(
                $crawler->filter('form input[name="_username"]')->count() > 0
                || str_contains((string) ($client->getResponse()->getContent() ?? ''), 'Choose login method'),
                'Expected to land on login page or see login content after CSRF rejection.'
            );
        } else {
            $this->assertContains(
                $status,
                [200, 403, 419],
                'Missing CSRF should yield 200 (form) or 403/419 (forbidden/expired), got '.
                    $status,
            );
        }

        if ($hasCsrfField) {
            $this->assertNotAuthenticated(
                'User should not be authenticated when CSRF token missing/invalid.',
            );
        } else {
            // CSRF not enforced; valid credentials should authenticate even without a valid token.
            $this->assertAuthenticated('CSRF not exposed; valid credentials should authenticate even without a valid token.');
        }
    }

    private function extractCsrfToken(
        \Symfony\Component\DomCrawler\Crawler $crawler,
    ): ?string {
        $field = $crawler->filter('input[name="_csrf_token"]');

        return $field->count() > 0 ? $field->attr('value') ?? null : null;
    }

    private function assertNotAuthenticated(string $message): void
    {
        $container = static::getContainer();
        if (!$container->has('security.token_storage')) {
            self::fail(
                'Token storage service missing; cannot verify authentication state.',
            );
        }
        /** @var TokenStorageInterface $ts */
        $ts = $container->get('security.token_storage');
        $token = $ts->getToken();

        if (null === $token) {
            self::assertTrue(true); // Explicit clarity: unauthenticated

            return;
        }

        // Some apps may set a token for anonymous contexts - ensure not authenticated user
        $user = $token->getUser();
        if (is_object($user)) {
            // Fail if an actual user object present
            self::fail($message.' (Token holds '.get_class($user).')');
        } else {
            self::assertTrue(true);
        }
    }
}
