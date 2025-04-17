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

    public string $domain = '';

    public function __construct(private readonly StreamArtistRepository $streamArtistRepository)
    {
    }

    public function mount(): void
    {
        $user = $this->getUser();
        if ($user instanceof UserInterface) {
            $this->domain = $_ENV['STREAM_DOMAIN'];
        }
    }

    public function getArtistStreams(Artist $artist): array
    {
        // Fetch the streams for the given artist from the repository
        // and assign them to the $streams property.
        $streams = $this->streamArtistRepository->findBy(['artist' => $artist]);

        $groupedStreams = [];
        foreach ($streams as $stream) {
            $streamId = $stream->getStream()->getId();

            if (!isset($groupedStreams[$streamId])) {
                $groupedStreams[$streamId] = [
                    'stream' => $stream->getStream(),
                    'items' => []
                ];
            }

            $groupedStreams[$streamId]['items'][] = $stream;
        }

        return array_values($groupedStreams); // Convert to indexed array
    }
}
