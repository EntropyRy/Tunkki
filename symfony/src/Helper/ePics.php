<?php

namespace App\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Helper for interacting with the Lychee (ePics) service.
 *
 * Improvements:
 * - Configurable base URL via EPICS_BASE_URL env (fallback to constant)
 * - Corrected user listing endpoint (UserManagement instead of Users)
 * - Added structured logging for easier debugging
 * - More defensive error handling & context logging
 */
class ePics
{
    /**
     * Default base URL (used if no env override).
     */
    private const API_BASE = "https://epics.entropy.fi";

    private string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ?LoggerInterface $logger = null,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim(
            $baseUrl ??
                ($_ENV["EPICS_BASE_URL"] ??
                    ($_SERVER["EPICS_BASE_URL"] ?? self::API_BASE)),
            "/",
        );
    }

    public function getRandomPic(): ?array
    {
        $pic = [];
        try {
            $initResponse = $this->client->request("GET", $this->baseUrl, [
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
                $this->log(
                    "warning",
                    "Failed to obtain session or XSRF token for random pic",
                    [
                        "session" => (bool) $sessionToken,
                        "xsrf" => (bool) $xsrfToken,
                    ],
                );
                return null;
            }

            $response = $this->client->request(
                "GET",
                $this->baseUrl . "/api/v2/Photo::random",
                [
                    "max_duration" => 10,
                    "headers" => $this->buildHeaders($sessionToken, $xsrfToken),
                ],
            );

            if ($response->getStatusCode() === 200) {
                $photoData = json_decode(
                    $response->getContent(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );

                if (!empty($photoData["size_variants"])) {
                    foreach (
                        ["medium2x", "medium", "thumb2x", "thumb"]
                        as $size
                    ) {
                        if (isset($photoData["size_variants"][$size])) {
                            $url = $photoData["size_variants"][$size]["url"];
                            if (!str_starts_with((string) $url, "http")) {
                                $url =
                                    $this->baseUrl .
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
            } else {
                $this->log("warning", "Random photo request failed", [
                    "status" => $response->getStatusCode(),
                ]);
            }
        } catch (TransportExceptionInterface $e) {
            $this->log("error", "Transport exception fetching random pic", [
                "exception" => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->log("error", "Unhandled exception fetching random pic", [
                "exception" => $e->getMessage(),
            ]);
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
                $this->log("error", "Failed to establish initial session");
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
                $this->log("error", "Missing admin credentials for ePics");
                return false;
            }

            $tokensAfterLogin = $this->login(
                $adminUser,
                $adminPass,
                $sessionToken,
                $xsrfToken,
            );
            if ($tokensAfterLogin === null) {
                $this->log("error", "Admin login to ePics failed", [
                    "adminUser" => $adminUser,
                ]);
                return false;
            }
            [$sessionToken, $xsrfToken] = $tokensAfterLogin;

            $headers = $this->buildHeaders($sessionToken, $xsrfToken);

            // Corrected: list users via UserManagement endpoint (previously /Users)
            $userId = null;
            $listResp = $this->client->request(
                "GET",
                $this->baseUrl . "/api/v2/UserManagement",
                [
                    "max_duration" => 10,
                    "headers" => $headers,
                ],
            );

            if ($listResp->getStatusCode() === 200) {
                $users = json_decode($listResp->getContent(false), true) ?? [];
                if (!\is_array($users)) {
                    $this->log(
                        "warning",
                        "Unexpected user list response structure",
                        ["type" => get_debug_type($users)],
                    );
                } else {
                    foreach ($users as $u) {
                        if (($u["username"] ?? null) === $username) {
                            $userId = (int) ($u["id"] ?? 0);
                            break;
                        }
                    }
                }
            } else {
                $this->log("error", "Failed to list users", [
                    "status" => $listResp->getStatusCode(),
                ]);
            }

            if ($userId) {
                $resp = $this->client->request(
                    "PATCH",
                    $this->baseUrl . "/api/v2/UserManagement",
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
                $ok =
                    $resp->getStatusCode() >= 200 &&
                    $resp->getStatusCode() < 300;
                if (!$ok) {
                    $this->log("error", "Failed to update existing user", [
                        "status" => $resp->getStatusCode(),
                        "username" => $username,
                    ]);
                }
                return $ok;
            }

            // User not found: create
            $create = $this->client->request(
                "POST",
                $this->baseUrl . "/api/v2/UserManagement",
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
            $created =
                $create->getStatusCode() >= 200 &&
                $create->getStatusCode() < 300;
            if (!$created) {
                $this->log("error", "Failed to create user", [
                    "status" => $create->getStatusCode(),
                    "username" => $username,
                ]);
            }
            return $created;
        } catch (TransportExceptionInterface $e) {
            $this->log("error", "Transport exception ensuring user", [
                "exception" => $e->getMessage(),
                "username" => $username,
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->log("error", "Unhandled exception ensuring user", [
                "exception" => $e->getMessage(),
                "username" => $username,
            ]);
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
            "Referer" => $this->baseUrl . "/",
            "Origin" => $this->baseUrl,
        ];
        return array_merge($headers, $extra);
    }

    /**
     * Establish anonymous session to get XSRF token and session cookie.
     * @return array{0:string,1:string}|null [sessionToken, xsrfToken] or null on failure
     */
    private function establishSession(): ?array
    {
        try {
            $initResponse = $this->client->request("GET", $this->baseUrl, [
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
                $this->log(
                    "error",
                    "Missing session or XSRF token after establishSession",
                    [
                        "session" => (bool) $sessionToken,
                        "xsrf" => (bool) $xsrfToken,
                    ],
                );
                return null;
            }

            return [$sessionToken, $xsrfToken];
        } catch (TransportExceptionInterface $e) {
            $this->log("error", "Transport exception establishing session", [
                "exception" => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->log("error", "Unhandled exception establishing session", [
                "exception" => $e->getMessage(),
            ]);
            return null;
        }
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
        try {
            $response = $this->client->request(
                "POST",
                $this->baseUrl . "/api/v2/Auth::login",
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
                $this->log("error", "Auth::login failed", [
                    "status" => $status,
                    "username" => $username,
                ]);
                return null;
            }

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
        } catch (TransportExceptionInterface $e) {
            $this->log("error", "Transport exception during login", [
                "exception" => $e->getMessage(),
                "username" => $username,
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->log("error", "Unhandled exception during login", [
                "exception" => $e->getMessage(),
                "username" => $username,
            ]);
            return null;
        }
    }

    private function log(
        string $level,
        string $message,
        array $context = [],
    ): void {
        if ($this->logger) {
            try {
                $this->logger->log($level, "[ePics] " . $message, $context);
            } catch (\Throwable) {
                // Swallow logger errors silently
            }
        }
    }
}
