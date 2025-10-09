<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Stream;
use App\Tests\_Base\FixturesWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the /api/stream-notifications webhook endpoint handled by
 * StreamNotificationController. The endpoint is used by the IceCast server
 * (or another streaming source) to signal stream start / stop events.
 *
 * Covered scenarios:
 *  1. Rejects unknown event type (400)
 *  2. Rejects request with invalid auth token (401)
 *  3. Accepts stream-start -> creates a Stream entity (online = true, filename set)
 *  4. Accepts stream-stop -> updates existing online Stream (online = false)
 *  5. Round‑trip: start then stop returns same stream id
 *  6. Invalid JSON / non-array payload returns 400
 */
final class StreamNotificationControllerTest extends FixturesWebTestCase
{
    private const TEST_TOKEN = 'test-token-123';
    // (Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static site-aware client)

    protected function setUp(): void
    {
        parent::setUp();
        // Provide the token expected by controller (it reads directly from $_ENV / $_SERVER)
        $_ENV['STREAM_NOTIFICATION_TOKEN'] = self::TEST_TOKEN;
        $_SERVER['STREAM_NOTIFICATION_TOKEN'] = self::TEST_TOKEN;

        $this->initSiteAwareClient();
        $this->client = $this->client();
    }

    public function testRejectsInvalidEventType(): void
    {
        $client = $this->client;
        $client->request(
            'POST',
            '/api/stream-notifications',
            server: [
                'HTTP_X-Stream-Auth-Token' => self::TEST_TOKEN,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['event' => 'not-a-valid-event'], \JSON_THROW_ON_ERROR),
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode(), 'Invalid event type should return 400.');

        $payload = json_decode($client->getResponse()->getContent() ?: 'null', true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testRejectsInvalidToken(): void
    {
        $client = $this->client;
        $client->request(
            'POST',
            '/api/stream-notifications',
            server: [
                'HTTP_X-Stream-Auth-Token' => 'wrong-token',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['event' => 'stream-start'], \JSON_THROW_ON_ERROR),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), 'Invalid token should return 401.');

        $payload = json_decode($client->getResponse()->getContent() ?: 'null', true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testStreamStartCreatesOnlineStreamAndStopUpdatesIt(): void
    {
        $client = $this->client;

        $initialCount = $this->em()->getRepository(Stream::class)->count([]);

        // --- START EVENT ---
        $startFile = 'live-session-001';
        $client->request(
            'POST',
            '/api/stream-notifications',
            server: [
                'HTTP_X-Stream-Auth-Token' => self::TEST_TOKEN,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'event' => 'stream-start',
                    'recording_file' => $startFile,
                    'timestamp' => time(),
                ],
                \JSON_THROW_ON_ERROR
            ),
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'stream-start should return 200.');
        $startPayload = json_decode($client->getResponse()->getContent() ?: 'null', true);
        $this->assertIsArray($startPayload);
        $this->assertSame('success', $startPayload['status'] ?? null);
        $this->assertSame('Stream started', $startPayload['message'] ?? null, 'Expected refined start message.');
        $this->assertArrayHasKey('event_id', $startPayload);
        $this->assertNotNull($startPayload['event_id'], 'stream-start should return a persisted stream id.');

        $this->assertSame(
            $initialCount + 1,
            $this->em()->getRepository(Stream::class)->count([]),
            'Exactly one new Stream entity should be created after start.'
        );

        /** @var Stream $stream */
        $stream = $this->em()->getRepository(Stream::class)->find($startPayload['event_id']);
        $this->assertInstanceOf(Stream::class, $stream);
        $this->assertTrue($stream->isOnline(), 'Newly created stream should be online.');
        $this->assertSame($startFile, $stream->getFilename());

        // --- STOP EVENT ---
        $client->request(
            'POST',
            '/api/stream-notifications',
            server: [
                'HTTP_X-Stream-Auth-Token' => self::TEST_TOKEN,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'event' => 'stream-stop',
                    'recording_file' => $startFile,
                    'timestamp' => time() + 60,
                ],
                \JSON_THROW_ON_ERROR
            ),
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'stream-stop should return 200.');
        $stopPayload = json_decode($client->getResponse()->getContent() ?: 'null', true);
        $this->assertIsArray($stopPayload);
        $this->assertSame('success', $stopPayload['status'] ?? null);
        $this->assertSame('Stream stopped', $stopPayload['message'] ?? null, 'Expected refined stop message.');
        $this->assertSame(
            $stream->getId(),
            $stopPayload['event_id'],
            'Stop should return the same stream id.'
        );

        // Instead of relying on entity state (which may appear stale due to test isolation),
        // assert via repository count that no streams remain online.
        $this->em()->clear();
        $onlineCount = $this->em()->getRepository(Stream::class)->count(['online' => true]);
        $this->assertSame(0, $onlineCount, 'There should be no online streams after stop.');
    }

    public function testStreamStopWithoutActiveStreamStillReturnsSuccess(): void
    {
        // Ensure there is no online stream
        $repo = $this->em()->getRepository(Stream::class);
        foreach ($repo->findAll() as $existing) {
            /** @var Stream $existing */
            if ($existing->isOnline()) {
                $existing->setOnline(false);
            }
        }
        $this->em()->flush();

        $client = $this->client;
        $client->request(
            'POST',
            '/api/stream-notifications',
            server: [
                'HTTP_X-Stream-Auth-Token' => self::TEST_TOKEN,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'event' => 'stream-stop',
                    'recording_file' => 'non-existent',
                    'timestamp' => time(),
                ],
                \JSON_THROW_ON_ERROR
            ),
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Stop without an active stream still returns 200 (soft warning).');
        $payload = json_decode($client->getResponse()->getContent() ?: 'null', true);
        $this->assertIsArray($payload);
        $this->assertSame('success', $payload['status'] ?? null);
        $this->assertSame('No active stream', $payload['message'] ?? null, 'Expected refined no-active message.');
        $this->assertNull($payload['event_id'], 'No active stream means null event_id in response.');
    }

    public function testInvalidJsonPayloadReturnsBadRequest(): void
    {
        $client = $this->client;
        $_ENV['STREAM_NOTIFICATION_TOKEN'] = self::TEST_TOKEN;
        $_SERVER['STREAM_NOTIFICATION_TOKEN'] = self::TEST_TOKEN;

        // Send a plain string (not JSON array/object)
        $client->request(
            'POST',
            '/api/stream-notifications',
            server: [
                'HTTP_X-Stream-Auth-Token' => self::TEST_TOKEN,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: 'not-json-array'
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode(), 'Non-JSON or non-array payload should return 400.');
        $payload = json_decode($client->getResponse()->getContent() ?: 'null', true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
    }
}
