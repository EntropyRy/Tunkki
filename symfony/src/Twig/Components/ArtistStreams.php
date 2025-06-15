<?php

namespace App\Twig\Components;

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
    ) {}

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
                    "overlapping_artists" => [],
                ];
            }

            $groupedStreams[$streamId]["items"][] = $stream;
        }

        // For each grouped stream, find overlapping artists
        foreach ($groupedStreams as $streamId => &$group) {
            $group["overlapping_artists"] = $this->getOverlappingArtists(
                $group["stream"],
                $artist,
                $group["items"]
            );
        }

        return array_values($groupedStreams); // Convert to indexed array
    }

    /**
     * @param mixed $stream
     * @param Artist $currentArtist
     * @param array $currentArtistItems
     */
    private function getOverlappingArtists(
        $stream,
        Artist $currentArtist,
        array $currentArtistItems
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

            // Check if this artist overlaps with any of the current artist's time slots
            foreach ($currentArtistItems as $currentItem) {
                if ($this->hasTimeOverlap($currentItem, $streamArtist)) {
                    // Avoid duplicates
                    $artistId = $streamArtist->getArtist()->getId();
                    if (!isset($overlappingArtists[$artistId])) {
                        $overlappingArtists[$artistId] = [
                            "artist" => $streamArtist->getArtist(),
                            "items" => [],
                        ];
                    }
                    $overlappingArtists[$artistId]["items"][] = $streamArtist;
                    break; // Found overlap, no need to check other time slots for this artist
                }
            }
        }

        return array_values($overlappingArtists);
    }

    /**
     * @param mixed $item1
     * @param mixed $item2
     */
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
