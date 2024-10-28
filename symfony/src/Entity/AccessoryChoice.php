<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table('AccessoryChoice')]
#[ORM\Entity]
class AccessoryChoice implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 190)]
    private string $name;

    #[ORM\Column(name: 'compensationPrice', type: 'integer')]
    private int $compensationPrice;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName($name): AccessoryChoice
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

    public function setCompensationPrice(int $compensationPrice): AccessoryChoice
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    public function getCompensationPrice(): int
    {
        return $this->compensationPrice;
    }
}
