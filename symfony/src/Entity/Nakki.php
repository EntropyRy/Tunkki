<?php

namespace App\Entity;

use App\Repository\NakkiRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NakkiRepository::class)
 */
class Nakki
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=NakkiDefinition::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $definition;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $startAt;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $endAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDefinition(): ?NakkiDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?NakkiDefinition $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }
}
