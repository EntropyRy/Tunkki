<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
class Location implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(length: 255)]
    private ?string $streetAddress = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): static
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    /**
     * Returns the name for a required locale code ('fi' or 'en').
     * Falls back to default name if translation missing or locale unsupported.
     */
    public function getNameByLocale(string $locale): ?string
    {
        $primary = strtolower(substr($locale, 0, 2));

        return match ($primary) {
            'en' => $this->nameEn ?: $this->name,
            default => $this->name,
        };
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name ?: 'N/A';
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    public function setStreetAddress(string $streetAddress): static
    {
        $this->streetAddress = $streetAddress;

        return $this;
    }
}
