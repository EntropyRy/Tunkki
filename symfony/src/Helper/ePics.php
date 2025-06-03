<?php

namespace App\Helper;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ePics
{
    private const string API_BASE = 'https://epics.entropy.fi';

    public function __construct(
        private readonly HttpClientInterface $client
    ) {
    }

    public function getRandomPic(): ?array
    {
        $pic = [];
        try {
            // First establish a session by visiting the main site to get cookies
            $initResponse = $this->client->request(
                'GET',
                self::API_BASE,
                [
                    'max_duration' => 5,
                ]
            );

            // Extract cookies from response
            $headers = $initResponse->getHeaders();

            // Initialize session token and XSRF token
            $sessionToken = null;
            $xsrfToken = null;

            if (isset($headers['set-cookie'])) {
                foreach ($headers['set-cookie'] as $cookie) {
                    // Extract XSRF token
                    if (str_starts_with($cookie, 'XSRF-TOKEN=')) {
                        $parts = explode(';', $cookie);
                        $tokenValue = substr($parts[0], 11); // 11 is length of 'XSRF-TOKEN='
                        $xsrfToken = str_replace('%3D', '=', $tokenValue);
                    }

                    // Extract session token
                    if (str_starts_with($cookie, 'lychee_session=')) {
                        $parts = explode(';', $cookie);
                        $sessionValue = substr($parts[0], 15); // 15 is length of 'lychee_session='
                        $sessionToken = str_replace('%3D', '=', $sessionValue);
                    }
                }
            }

            // If we don't have what we need, return null
            if (!$sessionToken || !$xsrfToken) {
                return null;
            }

            // Make request to the v2 API with proper headers and cookies
            $response = $this->client->request(
                'GET',
                self::API_BASE . '/api/v2/Photo::random',
                [
                    'max_duration' => 10,
                    'headers' => [
                        'Cookie' => 'lychee_session=' . $sessionToken, // Only include the session token
                        'X-XSRF-TOKEN' => $xsrfToken, // XSRF token as header, not cookie
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Referer' => self::API_BASE . '/',
                    ]
                ]
            );

            if ($response->getStatusCode() == 200) {
                $photoData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

                // Process the response format - try different size variants
                if (!empty($photoData['size_variants'])) {
                    // Try different size variants in order of preference
                    foreach (['medium2x', 'medium', 'thumb2x', 'thumb'] as $size) {
                        if (isset($photoData['size_variants'][$size])) {
                            // Ensure the URL is complete
                            $url = $photoData['size_variants'][$size]['url'];
                            // Add base URL if the URL is relative
                            if (!str_starts_with($url, 'http')) {
                                $url = self::API_BASE . '/' . ltrim($url, '/');
                            }
                            $pic['url'] = $url;
                            $pic['taken'] = $photoData['taken_at'] ?? $photoData['created_at'] ?? null;
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
}
