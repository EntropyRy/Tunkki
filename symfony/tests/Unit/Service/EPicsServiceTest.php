<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EPicsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EPicsServiceTest extends TestCase
{
    private MockHttpClient $client;
    private EPicsService $service;

    protected function setUp(): void
    {
        $this->client = new MockHttpClient();
        // Set environment variables for testing
        $_ENV['EPICS_BASE_URL'] = 'https://epics.test.local';
        $_ENV['EPICS_ADMIN_USER'] = 'admin@test.com';
        $_ENV['EPICS_ADMIN_PASSWORD'] = 'admin_password';

        $this->service = new EPicsService($this->client);
    }

    public function testServiceConstructsCorrectly(): void
    {
        $service = new EPicsService($this->client);

        $this->assertInstanceOf(EPicsService::class, $service);
    }

    public function testGetRandomPhotoReturnsNullWhenSessionFails(): void
    {
        // No set-cookie headers in response
        $this->client->setResponseFactory([
            new MockResponse('', ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testGetRandomPhotoReturnsNullWhenPhotoRequestFails(): void
    {
        $this->client->setResponseFactory([
            // Session establishment
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            // Photo request fails
            new MockResponse('', ['http_code' => 404]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testGetRandomPhotoReturnsPhotoDataOnSuccess(): void
    {
        $photoData = [
            'size_variants' => [
                'medium' => [
                    'url' => '/uploads/medium/photo.jpg',
                ],
            ],
            'taken_at' => '2025-01-01T12:00:00+00:00',
        ];

        $this->client->setResponseFactory([
            // Session establishment
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            // Photo request succeeds
            new MockResponse(json_encode($photoData), ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('taken', $result);
        $this->assertStringContainsString('https://epics.test.local/uploads/medium/photo.jpg', $result['url']);
        $this->assertSame('2025-01-01T12:00:00+00:00', $result['taken']);
    }

    public function testGetRandomPhotoHandlesAbsoluteUrl(): void
    {
        $photoData = [
            'size_variants' => [
                'medium' => [
                    'url' => 'https://cdn.example.com/photo.jpg',
                ],
            ],
            'created_at' => '2025-01-02T10:00:00+00:00',
        ];

        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            new MockResponse(json_encode($photoData), ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertIsArray($result);
        $this->assertSame('https://cdn.example.com/photo.jpg', $result['url']);
        $this->assertSame('2025-01-02T10:00:00+00:00', $result['taken']);
    }

    public function testGetRandomPhotoPrefersMedium2xSize(): void
    {
        $photoData = [
            'size_variants' => [
                'thumb' => ['url' => '/thumb.jpg'],
                'medium' => ['url' => '/medium.jpg'],
                'medium2x' => ['url' => '/medium2x.jpg'],
            ],
            'taken_at' => '2025-01-01T12:00:00+00:00',
        ];

        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            new MockResponse(json_encode($photoData), ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertStringContainsString('medium2x.jpg', $result['url']);
    }

    public function testCreateOrUpdateUserPasswordReturnsFalseWhenSessionFails(): void
    {
        $this->client->setResponseFactory([
            new MockResponse('', ['http_code' => 200]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'newpassword');

        $this->assertFalse($result);
    }

    public function testCreateOrUpdateUserPasswordReturnsFalseWhenLoginFails(): void
    {
        $this->client->setResponseFactory([
            // Session establishment
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            // Login fails
            new MockResponse('', ['http_code' => 401]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'newpassword');

        $this->assertFalse($result);
    }

    public function testCreateOrUpdateUserPasswordCreatesNewUserWhenNotFound(): void
    {
        $this->client->setResponseFactory([
            // Session establishment
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            // Login succeeds
            new MockResponse('{"success":true}', ['http_code' => 200]),
            // User list - empty
            new MockResponse('[]', ['http_code' => 200]),
            // Create user succeeds
            new MockResponse('{"id":123}', ['http_code' => 201]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('newuser@example.com', 'password123');

        $this->assertTrue($result);
    }

    public function testCreateOrUpdateUserPasswordUpdatesExistingUser(): void
    {
        $userList = [
            ['id' => 123, 'username' => 'existing@example.com'],
        ];

        $this->client->setResponseFactory([
            // Session establishment
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            // Login succeeds
            new MockResponse('{"success":true}', ['http_code' => 200]),
            // User list
            new MockResponse(json_encode($userList), ['http_code' => 200]),
            // Update password succeeds
            new MockResponse('{"success":true}', ['http_code' => 200]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('existing@example.com', 'newpassword');

        $this->assertTrue($result);
    }

    public function testCreateOrUpdateUserPasswordReturnsFalseWhenUpdateFails(): void
    {
        $userList = [
            ['id' => 123, 'username' => 'existing@example.com'],
        ];

        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            new MockResponse('{"success":true}', ['http_code' => 200]),
            new MockResponse(json_encode($userList), ['http_code' => 200]),
            new MockResponse('', ['http_code' => 500]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('existing@example.com', 'newpassword');

        $this->assertFalse($result);
    }

    public function testGetRandomPhotoReturnsNullWithEmptySizeVariants(): void
    {
        $photoData = [
            'size_variants' => [],
            'taken_at' => '2025-01-01T12:00:00+00:00',
        ];

        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN=test_xsrf',
                    ],
                ],
            ]),
            new MockResponse(json_encode($photoData), ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }
}
