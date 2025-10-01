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
    #[Route('/api/stream-notifications', name: 'stream_notifications', methods: ['POST'])]
    public function handleStreamNotification(
        Request $request,
        LoggerInterface $logger,
        StreamRepository $streamRepository,
    ): Response {
        // Get the request content (JSON)
        $data = json_decode($request->getContent(), true);

        // Validate the request
        if (!isset($data['event']) || !in_array($data['event'], ['stream-start', 'stream-stop'])) {
            return $this->json(['error' => 'Invalid event type'], Response::HTTP_BAD_REQUEST);
        }

        // Validate authentication token
        $token = $request->headers->get('X-Stream-Auth-Token');
        if ($token !== $_ENV['STREAM_NOTIFICATION_TOKEN']) {
            $logger->warning('Unauthorized stream notification attempt', [
                'ip' => $request->getClientIp(),
                'event' => $data['event'],
            ]);

            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Log the event
        $logger->info('Stream event received', [
            'event' => $data['event'],
            'timestamp' => $data['timestamp'] ?? 'unknown',
            'recording_file' => $data['recording_file'] ?? 'unknown',
        ]);

        $stream = null;

        if ('stream-start' === $data['event']) {
            // Save to database
            $stream = new Stream();
            $stream->setOnline(true);
            $stream->setFilename($data['recording_file'] ?? null);
            $streamRepository->save($stream, true);
        } elseif ('stream-stop' === $data['event']) {
            $stream = $streamRepository->findOneBy(['online' => true]);
            if (null !== $stream) {
                $stream->setOnline(false);
                foreach ($stream->getArtists() as $artist) {
                    $artist->setStoppedAt(new \DateTimeImmutable());
                }
                $streamRepository->save($stream, true);
            } else {
                $logger->warning('Stream stop event received but no matching stream found', [
                    'recording_file' => $data['recording_file'] ?? null,
                ]);
            }
        }

        // Return success response
        return $this->json([
            'status' => 'success',
            'message' => 'Stream notification received',
            'event_id' => (null !== $stream ? $stream->getId() : null),
        ]);
    }
}
