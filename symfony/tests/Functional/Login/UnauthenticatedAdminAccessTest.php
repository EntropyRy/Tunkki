<?php

declare(strict_types=1);

namespace App\Tests\Functional\Login;

use App\Tests\_Base\FixturesWebTestCase;

/**
 * UnauthenticatedAdminAccessTest.
 *
 * Verifies that protected admin routes are NOT accessible without authentication.
 *
 * Roadmap alignment:
 *  - Task #20 (Negative path tests: Unauthenticated access to admin)
 *  - Task #28 (Security boundary assertions)
 *  - Complements the positive cases in AdminAccessTest (authenticated scenarios).
 *
 * Expectations (adjust if your security config differs):
 *  - Visiting an admin route while unauthenticated results in either:
 *      * A redirect (302/303) to /login (or a locale-specific login path), OR
 *      * An HTTP 401/403 (if your firewall is configured to deny instead of redirect).
 *
 * If your application uses a different login path (e.g. /en/login or /auth/login),
 * update LOGIN_PATH_CANDIDATES accordingly.
 */
final class UnauthenticatedAdminAccessTest extends FixturesWebTestCase
{
    /**
     * Override default client creation to reuse the initialized site-aware client.
     * Prevents a second kernel boot (WebTestCase forbids booting before createClient()).
     */
    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client;
    }

    /**
     * Candidate admin entrypoints to probe. Adjust if your routing differs.
     *
     * @return iterable<string,array{0:string}>
     */
    public static function provideAdminPaths(): iterable
    {
        yield 'dashboard_root_slash' => ['/admin/'];
        yield 'dashboard_default' => ['/admin/dashboard'];
    }

    /**
     * Potential login paths the application may redirect to.
     *
     * Add/remove entries if your project localizes or customizes the login route.
     */
    private const LOGIN_PATH_CANDIDATES = [
        '/login',
        '/en/login',
        '/fi/login',
    ];

    #[\PHPUnit\Framework\Attributes\DataProvider('provideAdminPaths')]
    public function testUnauthenticatedAccessIsRedirectedOrDenied(string $adminPath): void
    {
        $client = $this->client;
        $client->request('GET', $adminPath);

        $status = $client->getResponse()->getStatusCode();

        // Acceptable outcomes:
        //  - 302/303 redirect to login
        //  - 401/403 direct denial
        self::assertTrue(
            \in_array($status, [301, 302, 303, 401, 403], true),
            \sprintf(
                'Unexpected status code %d for unauthenticated admin request (%s). Expected redirect (302/303) or denial (401/403).',
                $status,
                $adminPath
            )
        );

        if (\in_array($status, [301, 302, 303], true)) {
            $location = $client->getResponse()->headers->get('Location') ?? '';
            self::assertNotEmpty(
                $location,
                \sprintf('Redirect response missing Location header for %s.', $adminPath)
            );

            // Normalize relative vs absolute URL
            $normalized = $this->normalizeLocation($location);

            // Assert the redirect target resembles a login page
            $matchesLogin = false;
            foreach (self::LOGIN_PATH_CANDIDATES as $candidate) {
                if (str_starts_with($normalized, $candidate)) {
                    $matchesLogin = true;
                    break;
                }
            }

            self::assertTrue(
                $matchesLogin,
                \sprintf(
                    'Expected redirect to a login path for %s, got Location="%s". Adjust LOGIN_PATH_CANDIDATES if the project uses a custom login route.',
                    $adminPath,
                    $location
                )
            );
        }
    }

    private function normalizeLocation(string $raw): string
    {
        // Strip scheme/host if absolute
        if (preg_match('#^https?://#i', $raw)) {
            $parts = parse_url($raw);
            $path = $parts['path'] ?? '/';
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return $path.$query;
        }

        return $raw;
    }
}
