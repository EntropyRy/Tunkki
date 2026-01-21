<?php

declare(strict_types=1);

namespace App\Entity\Rental\Inventory;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table('AccessoryChoice')]
#[ORM\Entity]
class AccessoryChoice implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 190)]
    private string $name = '';

    #[ORM\Column(name: 'compensationPrice', type: Types::INTEGER)]
    private int $compensationPrice;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): self
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

    public function setCompensationPrice(int $compensationPrice): self
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    public function getCompensationPrice(): int
    {
        return $this->compensationPrice;
    }
}
