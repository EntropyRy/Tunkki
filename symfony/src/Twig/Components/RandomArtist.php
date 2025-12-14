<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Artist;
use App\Repository\ArtistRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class RandomArtist
{
    public ?Artist $artist = null;

    public function __construct(
        private readonly ArtistRepository $artistRepository,
    ) {
    }

    public function mount(): void
    {
        $artists = $this->artistRepository->findBy(['copyForArchive' => false]);
        if ($artists !== []) {
            shuffle($artists);
            $this->artist = array_pop($artists);
        }
    }
}
