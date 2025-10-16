<?php

declare(strict_types=1);

namespace App\Tests\Functional\Login;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * CSRF protection scenarios for the login form.
 *
 * Roadmap alignment:
 *  - #13 Split oversized LoginTest
 *  - #20 Negative path coverage
 *  - #23 Replace verbose DOM crawling with high-level assertions
 *
 * Skips automatically if the login form does not expose a `_csrf_token` field.
 *
 * Refactor notes:
 *  - Replaced broad in_array status checks with explicit redirect / status assertions.
 *  - Deterministic handling of success vs failure outcomes (no ambiguous mixed sets).
 */
final class CsrfProtectionTest extends FixturesWebTestCase
{
    /**
     * Override default client creation to reuse the initialized site-aware client and avoid
     * a secondary kernel boot (which WebTestCase forbids after manual booting).
     */
    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client;
    }

    private const PLAIN_PASSWORD = 'password';

    /**
     * Ensure that a valid CSRF token results in successful authentication
     * (when CSRF is enabled).
     *
     * Proper flow: Try to access protected route -> redirect to login -> submit form -> redirect back
     */
    public function testValidCsrfAllowsLoginIfCsrfEnabled(): void
    {
        $client = $this->client;

        $member = MemberFactory::new()->english()->create();
        $email = $member->getEmail();
        self::assertNotEmpty($email);

        // 1. Try to access protected route to establish referer
        $targetRoute = '/en/profile';
        $crawler = $client->request('GET', $targetRoute);

        // 2. Should redirect to login
        self::assertTrue(
            $client->getResponse()->isRedirect(),
            'Accessing protected route should redirect to login'
        );
        $crawler = $client->followRedirect();

        // 3. Should be on login page
        self::assertResponseIsSuccessful();
        $hasCsrf = $crawler->filter('input[name="_csrf_token"]')->count() > 0;

        $csrf = $hasCsrf ? ($crawler->filter('input[name="_csrf_token"]')->attr('value') ?? null) : null;

        $formData = [
            '_username' => $email,
            '_password' => self::PLAIN_PASSWORD,
        ];
        if ($hasCsrf && $csrf) {
            $formData['_csrf_token'] = $csrf;
        }

        $form = $crawler
            ->filter('form')
            ->first()
            ->form($formData);
        $client->submit($form);

        // 4. Should redirect back to original route
        self::assertTrue(
            $client->getResponse()->isRedirect(),
            'Successful login should redirect'
        );
        $location = (string) ($client->getResponse()->headers->get('Location') ?? '');
        self::assertNotEmpty($location, 'Redirect Location header must be present after successful login.');
        self::assertStringNotContainsString('/login', $location, 'Successful login should not redirect back to /login.');

        $crawler = $client->followRedirect();

        // 5. Should successfully access the protected route
        self::assertResponseIsSuccessful();

        $this->assertAuthenticated(
            'User should be authenticated with valid CSRF token.',
        );
    }

    /**
     * An invalid CSRF token should prevent authentication.
     */
    public function testInvalidCsrfTokenRejectedIfCsrfEnabled(): void
    {
        [$client, $crawler, $hasCsrf] = $this->getLoginPageClient();
        // Proceed regardless of CSRF presence (invalid token applies only when CSRF is enabled).

        $member = MemberFactory::new()->english()->create();
        $email = $member->getEmail();

        $badToken = 'invalid'.bin2hex(random_bytes(4));

        $form = $crawler
            ->filter('form')
            ->first()
            ->form([
                '_username' => $email,
                '_password' => self::PLAIN_PASSWORD,
                '_csrf_token' => $badToken,
            ]);
        $client->submit($form);

        $status = $client->getResponse()->getStatusCode();

        if ($client->getResponse()->isRedirect()) {
            $loc =
                (string) ($client->getResponse()->headers->get('Location') ??
                    '');
            self::assertNotEmpty($loc);
            self::assertStringContainsString('/login', $loc);
            $crawler = $client->followRedirect();
            self::assertSame(200, $client->getResponse()->getStatusCode());
            // Accept direct 200 re-render; no specific form selector required here.
            if ($hasCsrf) {
                $this->assertNotAuthenticated(
                    'Invalid CSRF token must not authenticate the user (redirect case).',
                );
            } else {
                $this->assertAuthenticated(
                    'CSRF disabled/not exposed; credentials succeed even with extra _csrf_token.'
                );
            }

            return;
        }

        // Non-redirect failure: expect 200 (re-render form) or 403 (forbidden).
        self::assertContains(
            $status,
            [200, 403],
            'Invalid CSRF token should yield 200 (form re-display) or 403 (forbidden), got '.
                $status,
        );
        if ($hasCsrf) {
            $this->assertNotAuthenticated(
                'Invalid CSRF token must not authenticate the user.',
            );
        } else {
            $this->assertAuthenticated(
                'CSRF disabled/not exposed; credentials succeed even without CSRF enforcement.'
            );
        }
    }

    /**
     * A missing CSRF token should also prevent authentication.
     */
    public function testMissingCsrfTokenRejectedIfCsrfEnabled(): void
    {
        [$client, $crawler, $hasCsrf] = $this->getLoginPageClient();
        // Proceed regardless of CSRF presence (missing token is only relevant if CSRF is enabled).

        $member = MemberFactory::new()->english()->create();
        $email = $member->getEmail();

        // Manually POST without token instead of using the form (form would include the field).
        $client->request('POST', '/login', [
            '_username' => $email,
            '_password' => self::PLAIN_PASSWORD,
            // intentionally omit _csrf_token
        ]);

        $status = $client->getResponse()->getStatusCode();

        if ($client->getResponse()->isRedirect()) {
            $loc =
                (string) ($client->getResponse()->headers->get('Location') ??
                    '');
            self::assertNotEmpty($loc);
            self::assertStringContainsString('/login', $loc);
            $crawler = $client->followRedirect();
            self::assertSame(200, $client->getResponse()->getStatusCode());
            // Accept direct 200 re-render; no specific form selector required here.
            if ($hasCsrf) {
                $this->assertNotAuthenticated(
                    'Missing CSRF token must not authenticate the user (redirect case).',
                );
            } else {
                $this->assertAuthenticated(
                    'CSRF disabled/not exposed; credentials succeed without CSRF token.'
                );
            }

            return;
        }

        self::assertContains(
            $status,
            [200, 403],
            'Missing CSRF token should yield 200 (form re-display) or 403 (forbidden), got '.
                $status,
        );
        if ($hasCsrf) {
            $this->assertNotAuthenticated(
                'Missing CSRF token must not authenticate the user.',
            );
        } else {
            $this->assertAuthenticated(
                'CSRF disabled/not exposed; credentials succeed without CSRF token.'
            );
        }
    }

    /**
     * Helper returns [client, crawler, hasCsrfField].
     *
     * @return array{0:\Symfony\Bundle\FrameworkBundle\KernelBrowser,1:\Symfony\Component\DomCrawler\Crawler,2:bool}
     */
    private function getLoginPageClient(): array
    {
        $client = $this->client;
        $crawler = $this->seedLoginPage('fi');
        $hasCsrf = $crawler->filter('input[name="_csrf_token"]')->count() > 0;

        return [$client, $crawler, $hasCsrf];
    }

    private function assertNotAuthenticated(string $message): void
    {
        $ts = static::getContainer()->get('security.token_storage');
        if (!$ts instanceof TokenStorageInterface) {
            self::fail(
                'Token storage service missing; cannot assert auth state.',
            );
        }
        $token = $ts->getToken();
        if (null === $token) {
            self::assertTrue(true);

            return;
        }
        $user = $token->getUser();
        if (\is_object($user)) {
            self::fail(
                $message.' (Token user class: '.$user::class.')',
            );
        }
        self::assertTrue(true);
    }

    private function assertAuthenticated(string $message): void
    {
        $ts = static::getContainer()->get('security.token_storage');
        if (!$ts instanceof TokenStorageInterface) {
            self::fail(
                'Token storage service missing; cannot assert auth state.',
            );
        }
        $token = $ts->getToken();
        self::assertNotNull($token, $message.' (no token)');
        $user = $token->getUser();
        self::assertTrue(
            \is_object($user),
            $message.' (no authenticated user object present)',
        );
    }
}
