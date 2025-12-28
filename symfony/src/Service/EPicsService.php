<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for interacting with the Lychee (ePics) photo gallery API.
 *
 * Provides:
 * - Random photo fetching for display
 * - User account creation and password management
 * - Session and authentication handling
 *
 * Configuration via environment variables (optional):
 * - EPICS_BASE_URL: Base URL for ePics API (default: https://epics.entropy.fi)
 * - EPICS_ADMIN_USER: Admin username for user management
 * - EPICS_ADMIN_PASSWORD: Admin password for user management
 */
final readonly class EPicsService implements EPicsServiceInterface
{
    private string $baseUrl;
    private string $adminUser;
    private string $adminPassword;

    public function __construct(
        private HttpClientInterface $client,
    ) {
        $this->baseUrl = rtrim(
            (string) ($_ENV['EPICS_BASE_URL'] ?? $_SERVER['EPICS_BASE_URL'] ?? 'https://epics.entropy.fi'),
            '/'
        );
        $this->adminUser = $_ENV['EPICS_ADMIN_USER'] ?? $_SERVER['EPICS_ADMIN_USER'] ?? '';
        $this->adminPassword = $_ENV['EPICS_ADMIN_PASSWORD'] ?? $_SERVER['EPICS_ADMIN_PASSWORD'] ?? '';
    }

    /**
     * Fetch a random photo from the ePics gallery.
     *
     * @return array{url: string, taken: string|null}|null Photo data with URL and timestamp, or null on failure
     */
    public function getRandomPhoto(): ?array
    {
        try {
            $tokens = $this->establishSession();
            if (null === $tokens) {
                return null;
            }
            [$sessionToken, $xsrfToken] = $tokens;

            $response = $this->client->request(
                'GET',
                $this->baseUrl.'/api/v2/Photo::random',
                [
                    'max_duration' => 10,
                    'headers' => $this->buildHeaders($sessionToken, $xsrfToken),
                ],
            );

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $photoData = json_decode(
                $response->getContent(),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            if (empty($photoData['size_variants'])) {
                return null;
            }

            // Prefer larger sizes first
            foreach (['medium2x', 'medium', 'thumb2x', 'thumb'] as $size) {
                if (isset($photoData['size_variants'][$size]['url'])) {
                    $url = $photoData['size_variants'][$size]['url'];
                    if (!str_starts_with((string) $url, 'http')) {
                        $url = $this->baseUrl.'/'.ltrim((string) $url, '/');
                    }

                    return [
                        'url' => $url,
                        'taken' => $photoData['taken_at'] ?? ($photoData['created_at'] ?? null),
                    ];
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Create or update an ePics user account with the given password.
     *
     * If the user exists, updates their password. If not, creates a new user with upload permissions.
     *
     * @param string $username Username (typically email or member username)
     * @param string $password Plain text password
     *
     * @return bool True on success, false on failure
     */
    public function createOrUpdateUserPassword(string $username, string $password): bool
    {
        $tokens = $this->establishSession();
        if (null === $tokens) {
            return false;
        }
        [$sessionToken, $xsrfToken] = $tokens;

        $tokensAfterLogin = $this->login($this->adminUser, $this->adminPassword, $sessionToken, $xsrfToken);
        if (null === $tokensAfterLogin) {
            return false;
        }
        [$sessionToken, $xsrfToken] = $tokensAfterLogin;

        $headers = $this->buildHeaders($sessionToken, $xsrfToken);

        // Check if user exists
        $userId = $this->findUserId($username, $headers);

        if ($userId) {
            // User exists - update password via PATCH
            return $this->updateUserPassword($userId, $username, $password, $headers);
        }

        // User doesn't exist - create new user
        return $this->createUser($username, $password, $headers);
    }

    /**
     * Find user ID by username.
     */
    private function findUserId(string $username, array $headers): ?int
    {
        try {
            $listResp = $this->client->request(
                'GET',
                $this->baseUrl.'/api/v2/UserManagement',
                [
                    'max_duration' => 10,
                    'headers' => $headers,
                ],
            );

            if (200 !== $listResp->getStatusCode()) {
                return null;
            }

            $users = json_decode($listResp->getContent(false), true) ?? [];
            if (!\is_array($users)) {
                return null;
            }

            foreach ($users as $user) {
                if (($user['username'] ?? null) === $username) {
                    return (int) ($user['id'] ?? 0) ?: null;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Update existing user's password.
     *
     * Updates password via PATCH endpoint using snake_case field names.
     * Username is required by API even though it's immutable.
     */
    private function updateUserPassword(int $userId, string $username, string $password, array $headers): bool
    {
        try {
            $resp = $this->client->request(
                'PATCH',
                $this->baseUrl.'/api/v2/UserManagement',
                [
                    'max_duration' => 10,
                    'headers' => $headers,
                    'json' => [
                        'id' => $userId,
                        'username' => $username,
                        'password' => $password,
                        'may_upload' => true,
                        'may_edit_own_settings' => true,
                    ],
                ],
            );

            return $resp->getStatusCode() >= 200 && $resp->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Create a new user.
     */
    private function createUser(string $username, string $password, array $headers): bool
    {
        try {
            $create = $this->client->request(
                'POST',
                $this->baseUrl.'/api/v2/UserManagement',
                [
                    'max_duration' => 10,
                    'headers' => $headers,
                    'json' => [
                        'username' => $username,
                        'password' => $password,
                        'may_upload' => true,
                        'may_edit_own_settings' => true,
                    ],
                ],
            );

            return $create->getStatusCode() >= 200 && $create->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Establish anonymous session to get XSRF token and session cookie.
     *
     * @return array{0: string, 1: string}|null [sessionToken, xsrfToken] or null on failure
     */
    private function establishSession(): ?array
    {
        try {
            $initResponse = $this->client->request('GET', $this->baseUrl, [
                'max_duration' => 5,
            ]);

            $headers = $initResponse->getHeaders();
            $sessionToken = $this->extractCookie($headers, 'lychee_session');
            $xsrfToken = $this->extractCookie($headers, 'XSRF-TOKEN');

            if (!$sessionToken || !$xsrfToken) {
                return null;
            }

            return [$sessionToken, $xsrfToken];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Login to Lychee API with given credentials.
     *
     * @return array{0: string, 1: string}|null Updated [sessionToken, xsrfToken] or null on failure
     */
    private function login(
        string $username,
        string $password,
        string $sessionToken,
        string $xsrfToken,
    ): ?array {
        try {
            $response = $this->client->request(
                'POST',
                $this->baseUrl.'/api/v2/Auth::login',
                [
                    'max_duration' => 10,
                    'headers' => $this->buildHeaders($sessionToken, $xsrfToken),
                    'json' => [
                        'username' => $username,
                        'password' => $password,
                    ],
                ],
            );

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            $headers = $response->getHeaders();
            $newSession = $this->extractCookie($headers, 'lychee_session') ?? $sessionToken;
            $newXsrf = $this->extractCookie($headers, 'XSRF-TOKEN') ?? $xsrfToken;

            return [$newSession, $newXsrf];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract cookie value from response headers.
     */
    private function extractCookie(array $headers, string $cookieName): ?string
    {
        if (!isset($headers['set-cookie'])) {
            return null;
        }

        $prefix = $cookieName.'=';
        $prefixLen = \strlen($prefix);

        foreach ($headers['set-cookie'] as $cookie) {
            if (str_starts_with((string) $cookie, $prefix)) {
                $parts = explode(';', (string) $cookie);
                $tokenValue = substr($parts[0], $prefixLen);

                return rawurldecode($tokenValue);
            }
        }

        return null;
    }

    /**
     * Build HTTP headers with session and XSRF tokens.
     */
    private function buildHeaders(string $sessionToken, string $xsrfToken): array
    {
        return [
            'Cookie' => 'lychee_session='.$sessionToken.'; XSRF-TOKEN='.rawurlencode($xsrfToken),
            'X-XSRF-TOKEN' => $xsrfToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => $this->baseUrl.'/',
            'Origin' => $this->baseUrl,
        ];
    }
}
