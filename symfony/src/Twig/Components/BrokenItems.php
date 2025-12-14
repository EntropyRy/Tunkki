<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\ItemRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class BrokenItems
{
    public array $broken = [];
    public bool $random = false;

    public function __construct(
        private readonly ItemRepository $itemRepository,
    ) {
    }

    public function mount(bool $random = false): void
    {
        $this->random = $random;

        $this->broken = $this->itemRepository->findBy(['needsFixing' => true, 'toSpareParts' => false]);
        if ($this->random) {
            shuffle($this->broken);
        }
    }
}
