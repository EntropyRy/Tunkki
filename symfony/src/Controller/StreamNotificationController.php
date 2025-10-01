<?php

namespace App\Controller;

use App\Entity\Stream;
use App\Repository\StreamRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StreamNotificationController extends AbstractController
{
    /**
     * IceCast webhook endpoint to start / stop the active stream state.
     *
     * Events:
     *  - stream-start: creates one new Stream (does NOT auto-stop older ones)
     *  - stream-stop : stops ALL currently online streams (defensive; normally only one)
     *
     * Notes:
     *  - Twig Live Components query the latest online stream; after stop there is none.
     *  - Inline logic kept (no separate service) per current scope.
     *  - Filename column is non-nullable in the entity, so we coerce to '' when missing.
     */
    #[Route('/api/stream-notifications', name: 'stream_notifications', methods: ['POST'])]
    public function handleStreamNotification(
        Request $request,
        LoggerInterface $logger,
        StreamRepository $streamRepository,
    ): Response {
        $raw = $request->getContent();
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $event = $data['event'] ?? null;
        if (!in_array($event, ['stream-start', 'stream-stop'], true)) {
            return $this->json(['error' => 'Invalid event type'], Response::HTTP_BAD_REQUEST);
        }

        // Shared secret auth (simple header token).
        $provided = (string) $request->headers->get('X-Stream-Auth-Token', '');
        $expected = (string) ($_ENV['STREAM_NOTIFICATION_TOKEN'] ?? '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            $logger->warning('Unauthorized stream notification attempt', [
                'ip' => $request->getClientIp(),
                'event' => $event,
            ]);

            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $logger->info('Stream event received', [
            'event' => $event,
            'timestamp' => $data['timestamp'] ?? null,
            'recording_file' => $data['recording_file'] ?? null,
        ]);

        $stream = null;
        $message = 'OK';

        if ('stream-start' === $event) {
            $stream = new Stream();
            $stream->setOnline(true);
            $stream->setFilename($data['recording_file'] ?? '');
            $streamRepository->save($stream, true);
            $message = 'Stream started';
        } else { // stream-stop
            $onlineStreams = $streamRepository->stopAllOnline();
            if (0 === count($onlineStreams)) {
                $logger->info('Stream stop: no active stream');
                $message = 'No active stream';
            } else {
                // Return the most recent (highest id) as representative
                usort($onlineStreams, static fn(Stream $a, Stream $b) => $b->getId() <=> $a->getId());
                $stream = $onlineStreams[0];
                $message = 'Stream stopped';
            }
        }

        return $this->json([
            'status' => 'success',
            'message' => $message,
            'event_id' => $stream ? $stream->getId() : null,
        ]);
    }
}
