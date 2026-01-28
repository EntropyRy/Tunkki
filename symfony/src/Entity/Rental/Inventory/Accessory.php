<?php

declare(strict_types=1);

namespace App\Entity\Rental\Inventory;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Accessory.
 */
#[ORM\Table(name: 'Accessory')]
#[ORM\Entity]
class Accessory implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AccessoryChoice::class, cascade: ['persist'])]
    private ?AccessoryChoice $name = null;

    #[ORM\Column(name: 'count', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    private string $count = '';

    /**
     * Get id.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set count.
     */
    public function setCount(string $count): static
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Get count.
     */
    public function getCount(): string
    {
        return $this->count;
    }

    /**
     * Set name.
     */
    public function setName(?AccessoryChoice $name = null): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     */
    public function getName(): ?AccessoryChoice
    {
        return $this->name;
    }

    public function getCompensationPrice(): ?int
    {
        return $this->name?->getCompensationPrice();
    }

    #[\Override]
    public function __toString(): string
    {
        $name = $this->name ?: 'n/a';
        $count = $this->count ?: '';

        return $count.' X '.$name;
    }
}
