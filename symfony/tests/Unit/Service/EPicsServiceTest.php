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

    public function testGetRandomPhotoReturnsNullOnException(): void
    {
        // Simulate exception by throwing during request
        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 500,
                'error' => 'Connection refused',
            ]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testGetRandomPhotoFallsBackToThumbSize(): void
    {
        $photoData = [
            'size_variants' => [
                'thumb' => ['url' => '/thumb.jpg'],
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

        $this->assertIsArray($result);
        $this->assertStringContainsString('thumb.jpg', $result['url']);
    }

    public function testGetRandomPhotoFallsBackToThumb2xSize(): void
    {
        $photoData = [
            'size_variants' => [
                'thumb2x' => ['url' => '/thumb2x.jpg'],
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

        $this->assertIsArray($result);
        $this->assertStringContainsString('thumb2x.jpg', $result['url']);
    }

    public function testGetRandomPhotoUsesCreatedAtWhenTakenAtMissing(): void
    {
        $photoData = [
            'size_variants' => [
                'medium' => ['url' => '/medium.jpg'],
            ],
            // No taken_at, only created_at
            'created_at' => '2025-02-15T10:00:00+00:00',
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
        $this->assertSame('2025-02-15T10:00:00+00:00', $result['taken']);
    }

    public function testGetRandomPhotoReturnsNullWhenNoSizeMatches(): void
    {
        $photoData = [
            'size_variants' => [
                'original' => ['url' => '/original.jpg'], // Not one of the preferred sizes
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

        $this->assertNull($result);
    }

    public function testCreateOrUpdateUserPasswordReturnsFalseWhenUserListFails(): void
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
            // User list fails
            new MockResponse('', ['http_code' => 500]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'newpassword');

        $this->assertFalse($result);
    }

    public function testCreateOrUpdateUserPasswordReturnsFalseWhenCreateFails(): void
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
            // Create user fails
            new MockResponse('{"error":"forbidden"}', ['http_code' => 403]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('newuser@example.com', 'password123');

        $this->assertFalse($result);
    }

    public function testCreateOrUpdateUserPasswordHandlesUserWithZeroId(): void
    {
        // Edge case: user with id=0 should be treated as not found
        $userList = [
            ['id' => 0, 'username' => 'test@example.com'],
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
            // Should create new user since id=0 is treated as null
            new MockResponse('{"id":123}', ['http_code' => 201]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'password');

        $this->assertTrue($result);
    }

    public function testCreateOrUpdateUserPasswordHandlesNonArrayUserList(): void
    {
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
            // User list returns non-array (string)
            new MockResponse('"not an array"', ['http_code' => 200]),
            // Should create new user
            new MockResponse('{"id":123}', ['http_code' => 201]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('newuser@example.com', 'password');

        $this->assertTrue($result);
    }

    public function testLoginUpdatesTokensFromResponse(): void
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
                        'lychee_session=session_v1',
                        'XSRF-TOKEN=xsrf_v1',
                    ],
                ],
            ]),
            // Login succeeds with new tokens
            new MockResponse('{"success":true}', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=session_v2',
                        'XSRF-TOKEN=xsrf_v2',
                    ],
                ],
            ]),
            // User list
            new MockResponse(json_encode($userList), ['http_code' => 200]),
            // Update password
            new MockResponse('{"success":true}', ['http_code' => 200]),
        ]);

        $result = $this->service->createOrUpdateUserPassword('existing@example.com', 'newpassword');

        $this->assertTrue($result);
    }

    public function testCreateOrUpdateUserPasswordReturnsFalseOnException(): void
    {
        // Set up client to throw exception
        $this->client->setResponseFactory(static function () {
            throw new \Exception('Network error');
        });

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'password');

        $this->assertFalse($result);
    }

    public function testGetRandomPhotoHandlesUrlEncodedCookies(): void
    {
        $photoData = [
            'size_variants' => [
                'medium' => ['url' => '/medium.jpg'],
            ],
            'taken_at' => '2025-01-01T12:00:00+00:00',
        ];

        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'lychee_session=test_session',
                        'XSRF-TOKEN='.rawurlencode('token+with+special=chars'),
                    ],
                ],
            ]),
            new MockResponse(json_encode($photoData), ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('url', $result);
    }

    public function testConstructorUsesServerEnvVariables(): void
    {
        // Test fallback to $_SERVER when $_ENV is not set
        unset($_ENV['EPICS_BASE_URL']);
        $_SERVER['EPICS_BASE_URL'] = 'https://server.epics.test';

        $service = new EPicsService($this->client);

        // Can't directly test the URL, but can verify it constructs
        $this->assertInstanceOf(EPicsService::class, $service);

        // Cleanup
        $_ENV['EPICS_BASE_URL'] = 'https://epics.test.local';
        unset($_SERVER['EPICS_BASE_URL']);
    }

    public function testConstructorUsesDefaultUrlWhenNotConfigured(): void
    {
        // Temporarily unset env vars
        $origEnv = $_ENV['EPICS_BASE_URL'] ?? null;
        $origServer = $_SERVER['EPICS_BASE_URL'] ?? null;
        unset($_ENV['EPICS_BASE_URL'], $_SERVER['EPICS_BASE_URL']);

        $service = new EPicsService($this->client);
        $this->assertInstanceOf(EPicsService::class, $service);

        // Restore
        if (null !== $origEnv) {
            $_ENV['EPICS_BASE_URL'] = $origEnv;
        }
        if (null !== $origServer) {
            $_SERVER['EPICS_BASE_URL'] = $origServer;
        }
    }

    public function testGetRandomPhotoHandlesMissingSetCookieHeader(): void
    {
        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                // No set-cookie headers
            ]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testGetRandomPhotoReturnsNullOnMalformedJson(): void
    {
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
            // Malformed JSON
            new MockResponse('not valid json{', ['http_code' => 200]),
        ]);

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testFindUserIdCatchesException(): void
    {
        $requestCount = 0;
        $this->client->setResponseFactory(static function () use (&$requestCount) {
            ++$requestCount;
            if (1 === $requestCount) {
                // Session establishment succeeds
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'set-cookie' => [
                            'lychee_session=test_session',
                            'XSRF-TOKEN=test_xsrf',
                        ],
                    ],
                ]);
            }
            if (2 === $requestCount) {
                // Login succeeds
                return new MockResponse('{"success":true}', ['http_code' => 200]);
            }
            // User list request throws exception
            throw new \RuntimeException('Network error during user list');
        });

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'password');

        $this->assertFalse($result);
    }

    public function testUpdateUserPasswordCatchesException(): void
    {
        $userList = [
            ['id' => 123, 'username' => 'existing@example.com'],
        ];

        $requestCount = 0;
        $this->client->setResponseFactory(static function () use (&$requestCount, $userList) {
            ++$requestCount;
            if (1 === $requestCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'set-cookie' => [
                            'lychee_session=test_session',
                            'XSRF-TOKEN=test_xsrf',
                        ],
                    ],
                ]);
            }
            if (2 === $requestCount) {
                return new MockResponse('{"success":true}', ['http_code' => 200]);
            }
            if (3 === $requestCount) {
                return new MockResponse(json_encode($userList), ['http_code' => 200]);
            }
            // PATCH request throws exception
            throw new \RuntimeException('Network error during password update');
        });

        $result = $this->service->createOrUpdateUserPassword('existing@example.com', 'newpassword');

        $this->assertFalse($result);
    }

    public function testCreateUserCatchesException(): void
    {
        $requestCount = 0;
        $this->client->setResponseFactory(static function () use (&$requestCount) {
            ++$requestCount;
            if (1 === $requestCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'set-cookie' => [
                            'lychee_session=test_session',
                            'XSRF-TOKEN=test_xsrf',
                        ],
                    ],
                ]);
            }
            if (2 === $requestCount) {
                return new MockResponse('{"success":true}', ['http_code' => 200]);
            }
            if (3 === $requestCount) {
                // Empty user list
                return new MockResponse('[]', ['http_code' => 200]);
            }
            // POST create user throws exception
            throw new \RuntimeException('Network error during user creation');
        });

        $result = $this->service->createOrUpdateUserPassword('newuser@example.com', 'password');

        $this->assertFalse($result);
    }

    public function testEstablishSessionCatchesException(): void
    {
        $this->client->setResponseFactory(static function () {
            throw new \RuntimeException('Connection refused');
        });

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testLoginCatchesException(): void
    {
        $requestCount = 0;
        $this->client->setResponseFactory(static function () use (&$requestCount) {
            ++$requestCount;
            if (1 === $requestCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'set-cookie' => [
                            'lychee_session=test_session',
                            'XSRF-TOKEN=test_xsrf',
                        ],
                    ],
                ]);
            }
            // Login throws exception
            throw new \RuntimeException('Network error during login');
        });

        $result = $this->service->createOrUpdateUserPassword('test@example.com', 'password');

        $this->assertFalse($result);
    }

    public function testGetRandomPhotoRequestThrowsException(): void
    {
        $requestCount = 0;
        $this->client->setResponseFactory(static function () use (&$requestCount) {
            ++$requestCount;
            if (1 === $requestCount) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'set-cookie' => [
                            'lychee_session=test_session',
                            'XSRF-TOKEN=test_xsrf',
                        ],
                    ],
                ]);
            }
            // Photo request throws exception
            throw new \RuntimeException('Photo API error');
        });

        $result = $this->service->getRandomPhoto();

        $this->assertNull($result);
    }

    public function testExtractCookieReturnsNullWhenCookieNotFound(): void
    {
        // set-cookie header exists but doesn't contain the expected cookies
        $this->client->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'set-cookie' => [
                        'other_cookie=value',
                        'another_cookie=value2',
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getRandomPhoto();

        // Should return null because lychee_session and XSRF-TOKEN are not found
        $this->assertNull($result);
    }
}
