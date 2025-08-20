<?php

namespace App\Helper;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ePics
{
    private const string API_BASE = "https://epics.entropy.fi";

    public function __construct(private readonly HttpClientInterface $client) {}

    public function getRandomPic(): ?array
    {
        $pic = [];
        try {
            // First establish a session by visiting the main site to get cookies
            $initResponse = $this->client->request("GET", self::API_BASE, [
                "max_duration" => 5,
            ]);

            // Extract cookies from response
            $headers = $initResponse->getHeaders();

            // Initialize session token and XSRF token
            $sessionToken = null;
            $xsrfToken = null;

            if (isset($headers["set-cookie"])) {
                foreach ($headers["set-cookie"] as $cookie) {
                    // Extract XSRF token
                    if (str_starts_with($cookie, "XSRF-TOKEN=")) {
                        $parts = explode(";", $cookie);
                        $tokenValue = substr($parts[0], 11); // 11 is length of 'XSRF-TOKEN='
                        $xsrfToken = rawurldecode($tokenValue);
                    }

                    // Extract session token
                    if (str_starts_with($cookie, "lychee_session=")) {
                        $parts = explode(";", $cookie);
                        $sessionValue = substr($parts[0], 15); // 15 is length of 'lychee_session='
                        $sessionToken = rawurldecode($sessionValue);
                    }
                }
            }

            // If we don't have what we need, return null
            if (!$sessionToken || !$xsrfToken) {
                return null;
            }

            // Make request to the v2 API with proper headers and cookies
            $response = $this->client->request(
                "GET",
                self::API_BASE . "/api/v2/Photo::random",
                [
                    "max_duration" => 10,
                    "headers" => $this->buildHeaders($sessionToken, $xsrfToken),
                ],
            );

            if ($response->getStatusCode() == 200) {
                $photoData = json_decode(
                    $response->getContent(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );

                // Process the response format - try different size variants
                if (!empty($photoData["size_variants"])) {
                    // Try different size variants in order of preference
                    foreach (
                        ["medium2x", "medium", "thumb2x", "thumb"]
                        as $size
                    ) {
                        if (isset($photoData["size_variants"][$size])) {
                            // Ensure the URL is complete
                            $url = $photoData["size_variants"][$size]["url"];
                            // Add base URL if the URL is relative
                            if (!str_starts_with((string) $url, "http")) {
                                $url =
                                    self::API_BASE .
                                    "/" .
                                    ltrim((string) $url, "/");
                            }
                            $pic["url"] = $url;
                            $pic["taken"] =
                                $photoData["taken_at"] ??
                                ($photoData["created_at"] ?? null);
                            return $pic;
                        }
                    }
                }
            }
        } catch (TransportExceptionInterface) {
            return null;
        }
        return null;
    }

    /**
     * Create or update a Lychee (ePics) account password for the given username (usually email).
     * Requires admin credentials configured via environment variables EPICS_ADMIN_USER and EPICS_ADMIN_PASSWORD.
     */
    public function createOrUpdateUserPassword(
        string $username,
        string $password,
    ): bool {
        return $this->ensureUserPasswordAndPerms($username, $password);
    }

    /**
     * Ensure a Lychee (ePics) user exists with the given username and has the provided password + permissions.
     * Uses Auth::login and UserManagement endpoints.
     */
    public function ensureUserPasswordAndPerms(
        string $username,
        string $password,
    ): bool {
        try {
            $tokens = $this->establishSession();
            if ($tokens === null) {
                return false;
            }
            [$sessionToken, $xsrfToken] = $tokens;

            $adminUser =
                $_ENV["EPICS_ADMIN_USER"] ??
                ($_SERVER["EPICS_ADMIN_USER"] ?? null);
            $adminPass =
                $_ENV["EPICS_ADMIN_PASSWORD"] ??
                ($_SERVER["EPICS_ADMIN_PASSWORD"] ?? null);

            if (!$adminUser || !$adminPass) {
                return false;
            }

            $tokensAfterLogin = $this->login(
                $adminUser,
                $adminPass,
                $sessionToken,
                $xsrfToken,
            );
            if ($tokensAfterLogin === null) {
                return false;
            }
            [$sessionToken, $xsrfToken] = $tokensAfterLogin;

            $headers = $this->buildHeaders($sessionToken, $xsrfToken);

            // Try to find existing user by username
            $userId = null;
            $listResp = $this->client->request(
                "GET",
                self::API_BASE . "/api/v2/Users",
                [
                    "max_duration" => 10,
                    "headers" => $headers,
                ],
            );
            if ($listResp->getStatusCode() === 200) {
                $users = json_decode($listResp->getContent(false), true) ?? [];
                foreach ($users as $u) {
                    if (($u["username"] ?? null) === $username) {
                        $userId = (int) ($u["id"] ?? 0);
                        break;
                    }
                }
            }

            if ($userId) {
                // Update password and permissions
                $resp = $this->client->request(
                    "PATCH",
                    self::API_BASE . "/api/v2/UserManagement",
                    [
                        "max_duration" => 10,
                        "headers" => $headers,
                        "json" => [
                            "id" => (string) $userId,
                            "username" => $username,
                            "password" => $password,
                            "may_upload" => true,
                            "may_edit_own_settings" => true,
                        ],
                    ],
                );
                return $resp->getStatusCode() >= 200 &&
                    $resp->getStatusCode() < 300;
            }

            // Create user if not found
            $create = $this->client->request(
                "POST",
                self::API_BASE . "/api/v2/UserManagement",
                [
                    "max_duration" => 10,
                    "headers" => $headers,
                    "json" => [
                        "username" => $username,
                        "password" => $password,
                        "may_upload" => true,
                        "may_edit_own_settings" => true,
                    ],
                ],
            );
            return $create->getStatusCode() >= 200 &&
                $create->getStatusCode() < 300;
        } catch (TransportExceptionInterface) {
            return false;
        }
    }

    private function buildHeaders(
        string $sessionToken,
        string $xsrfToken,
        array $extra = [],
    ): array {
        $cookies = [
            "lychee_session=" . $sessionToken,
            "XSRF-TOKEN=" . rawurlencode($xsrfToken),
        ];
        $headers = [
            "Cookie" => implode("; ", $cookies),
            "X-XSRF-TOKEN" => $xsrfToken,
            "Accept" => "application/json",
            "Content-Type" => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
            "Referer" => self::API_BASE . "/",
            "Origin" => self::API_BASE,
        ];
        return array_merge($headers, $extra);
    }

    /**
     * Establish anonymous session to get XSRF token and session cookie.
     * @return array{0:string,1:string}|null [sessionToken, xsrfToken] or null on failure
     */
    private function establishSession(): ?array
    {
        $initResponse = $this->client->request("GET", self::API_BASE, [
            "max_duration" => 5,
        ]);

        $headers = $initResponse->getHeaders();
        $sessionToken = null;
        $xsrfToken = null;

        if (isset($headers["set-cookie"])) {
            foreach ($headers["set-cookie"] as $cookie) {
                if (str_starts_with($cookie, "XSRF-TOKEN=")) {
                    $parts = explode(";", $cookie);
                    $tokenValue = substr($parts[0], 11);
                    $xsrfToken = rawurldecode($tokenValue);
                }
                if (str_starts_with($cookie, "lychee_session=")) {
                    $parts = explode(";", $cookie);
                    $sessionValue = substr($parts[0], 15);
                    $sessionToken = rawurldecode($sessionValue);
                }
            }
        }

        if (!$sessionToken || !$xsrfToken) {
            return null;
        }

        return [$sessionToken, $xsrfToken];
    }

    /**
     * Login to Lychee API with given credentials.
     * Returns updated [sessionToken, xsrfToken] if cookies rotated or the original tokens on 2xx.
     * @return array{0:string,1:string}|null
     */
    private function login(
        string $username,
        string $password,
        string $sessionToken,
        string $xsrfToken,
    ): ?array {
        $response = $this->client->request(
            "POST",
            self::API_BASE . "/api/v2/Auth::login",
            [
                "max_duration" => 10,
                "headers" => $this->buildHeaders($sessionToken, $xsrfToken),
                "json" => [
                    "username" => $username,
                    "password" => $password,
                ],
            ],
        );

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return null;
        }

        // Refresh cookies/tokens after login if server rotated them
        $headers = $response->getHeaders();
        $newSession = $sessionToken;
        $newXsrf = $xsrfToken;

        if (isset($headers["set-cookie"])) {
            foreach ($headers["set-cookie"] as $cookie) {
                if (str_starts_with($cookie, "XSRF-TOKEN=")) {
                    $parts = explode(";", $cookie);
                    $tokenValue = substr($parts[0], 11);
                    $newXsrf = rawurldecode($tokenValue);
                }
                if (str_starts_with($cookie, "lychee_session=")) {
                    $parts = explode(";", $cookie);
                    $sessionValue = substr($parts[0], 15);
                    $newSession = rawurldecode($sessionValue);
                }
            }
        }

        return [$newSession, $newXsrf];
    }
}
