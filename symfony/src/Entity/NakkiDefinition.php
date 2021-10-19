<?php

namespace App\Entity;

use App\Repository\NakkiDefinitionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NakkiDefinitionRepository::class)
 */
class NakkiDefinition
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nameFi;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nameEn;

    /**
     * @ORM\Column(type="text")
     */
    private $DescriptionFi;

    /**
     * @ORM\Column(type="text")
     */
    private $DescriptionEn;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $onlyForActiveMembers = false;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function __toString() 
    {
        return $this->nameEn;
    }
    public function getName($lang): ?string
    {
        $func = 'name'. ucfirst($lang);
        return $this->{$func};
    }

    public function getDescription($lang): ?string
    {
        $func = 'Description'. ucfirst($lang);
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
