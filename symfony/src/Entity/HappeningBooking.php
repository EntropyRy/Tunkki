<?php

namespace App\Entity;

use App\Repository\HappeningBookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HappeningBookingRepository::class)]
class HappeningBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: "Member", inversedBy: 'happeningBooking', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Member $member = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Happening $happening = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member = null): self
    {
        $this->member = $member;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getHappening(): ?Happening
    {
        return $this->happening;
    }

    public function setHappening(?Happening $happening): self
    {
        $this->happening = $happening;

        return $this;
    }

    public function __toString()
    {
        return $this->createdAt->format('d.m. H:i') . ' ' . $this->member;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
