<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\ArtistRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ArtistList
{
    public array $artists = [];
    public int $count = 0;
    public bool $box = false;

    public function __construct(
        private readonly ArtistRepository $artistRepository,
    ) {}

    public function mount(bool $box = false): void
    {
        $this->box = $box;

        $this->artists['DJ'] = $this->artistRepository->findBy(['copyForArchive' => false, 'type' => 'DJ'], ['name' => 'ASC']);
        $this->artists['LIVE'] = $this->artistRepository->findBy(['copyForArchive' => false, 'type' => 'LIVE'], ['name' => 'ASC']);
        $this->artists['VJ'] = $this->artistRepository->findBy(['copyForArchive' => false, 'type' => 'VJ'], ['name' => 'ASC']);
        $this->artists['ART'] = $this->artistRepository->findBy(['copyForArchive' => false, 'type' => 'ART'], ['name' => 'ASC']);

        $this->count = array_sum(array_map(count(...), $this->artists));
    }
}
