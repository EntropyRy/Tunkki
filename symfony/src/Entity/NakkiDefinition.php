<?php

namespace App\Entity;

use App\Repository\NakkiDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NakkiDefinitionRepository::class)]
class NakkiDefinition implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $nameFi = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $nameEn = null;

    #[ORM\Column(type: 'text')]
    private ?string $DescriptionFi = null;

    #[ORM\Column(type: 'text')]
    private ?string $DescriptionEn = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $onlyForActiveMembers = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->nameEn;
    }

    public function getName($lang): ?string
    {
        $func = 'name'.ucfirst((string) $lang);

        return $this->{$func};
    }

    public function getDescription($lang): ?string
    {
        $func = 'Description'.ucfirst((string) $lang);

        return $this->{$func};
    }

    public function getNameFi(): ?string
    {
        return $this->nameFi;
    }

    public function setNameFi(string $nameFi): self
    {
        $this->nameFi = $nameFi;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(string $nameEn): self
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getDescriptionFi(): ?string
    {
        return $this->DescriptionFi;
    }

    public function setDescriptionFi(string $DescriptionFi): self
    {
        $this->DescriptionFi = $DescriptionFi;

        return $this;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->DescriptionEn;
    }

    public function setDescriptionEn(string $DescriptionEn): self
    {
        $this->DescriptionEn = $DescriptionEn;

        return $this;
    }

    public function getOnlyForActiveMembers(): ?bool
    {
        return $this->onlyForActiveMembers;
    }

    public function setOnlyForActiveMembers(?bool $onlyForActiveMembers): self
    {
        $this->onlyForActiveMembers = $onlyForActiveMembers;

        return $this;
    }
}
