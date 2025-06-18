<?php

namespace App\Twig\Components;

use App\Entity\Stream;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Artist;
use App\Repository\StreamArtistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ArtistStreams extends AbstractController
{
    #[LiveProp(updateFromParent: true)]
    public Artist $artist;

    public string $domain = "";

    public function __construct(
        private readonly StreamArtistRepository $streamArtistRepository
    ) {
    }

    public function mount(): void
    {
        $user = $this->getUser();
        if ($user instanceof UserInterface) {
            $this->domain = $_ENV["STREAM_DOMAIN"];
        }
    }

    public function getArtistStreams(Artist $artist): array
    {
        // Fetch the streams for the given artist from the repository
        // and assign them to the $streams property.
        $streams = $this->streamArtistRepository->findBy(["artist" => $artist]);

        $groupedStreams = [];
        foreach ($streams as $stream) {
            $streamId = $stream->getStream()->getId();

            if (!isset($groupedStreams[$streamId])) {
                $groupedStreams[$streamId] = [
                    "stream" => $stream->getStream(),
                    "items" => [],
                ];
            }

            $groupedStreams[$streamId]["items"][] = $stream;
        }

        // For each grouped stream, add overlapping artists to each item
        foreach ($groupedStreams as &$group) {
            foreach ($group["items"] as &$item) {
                $item['overlappingArtists'] = $this->getOverlappingArtistsForTimeSlot(
                    $group["stream"],
                    $artist,
                    $item
                );
            }
        }

        return array_values($groupedStreams); // Convert to indexed array
    }

    private function getOverlappingArtistsForTimeSlot(
        ?Stream $stream,
        Artist $currentArtist,
        $currentItem
    ): array {
        // Get all stream artists for this stream except the current artist
        $allStreamArtists = $this->streamArtistRepository->findBy([
            "stream" => $stream,
        ]);

        $overlappingArtists = [];

        foreach ($allStreamArtists as $streamArtist) {
            // Skip if it's the same artist
            if (
                $streamArtist->getArtist()->getId() === $currentArtist->getId()
            ) {
                continue;
            }

            // Check if this artist overlaps with the current time slot
            if ($this->hasTimeOverlap($currentItem, $streamArtist)) {
                $overlappingArtists[] = $streamArtist->getArtist()->getName();
            }
        }

        return $overlappingArtists;
    }

    private function hasTimeOverlap($item1, $item2): bool
    {
        $start1 = $item1->getStartedAt();
        $end1 = $item1->getStoppedAt();
        $start2 = $item2->getStartedAt();
        $end2 = $item2->getStoppedAt();

        // If either doesn't have an end time, assume it's still ongoing
        if (!$end1) {
            $end1 = new \DateTimeImmutable(); // Current time
        }
        if (!$end2) {
            $end2 = new \DateTimeImmutable(); // Current time
        }

        // Check for overlap: start1 < end2 AND start2 < end1
        return $start1 < $end2 && $start2 < $end1;
    }
}
