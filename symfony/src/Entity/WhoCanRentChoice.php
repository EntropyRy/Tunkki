<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * WhoCanRentCoice
 */
#[ORM\Table(name: 'WhoCanRentChoice')]
#[ORM\Entity]
class WhoCanRentChoice implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 190)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }
    #[\Override]
    public function __toString(): string
    {
        return $this->name ?: '';
    }
}
