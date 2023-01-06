<?php

namespace App\Entity;

use App\Repository\DoorLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoorLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DoorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'doorLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\Member $member = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface|\DateTime|null $createdAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $message = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }
    #[ORM\PrePersist]
    public function prePersist()
    {
        $this->createdAt = new \DateTime();
    }
}
