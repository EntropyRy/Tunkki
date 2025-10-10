<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RSVPRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RSVPRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RSVP implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'RSVPs')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'RSVPs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Member $member = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;

        return $this;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getAvailableLastName(): string
    {
        if ($this->getMember() instanceof Member) {
            return ucfirst((string) $this->getMember()->getLastname());
        }

        return ucfirst((string) $this->lastName);
    }

    public function getAvailableEmail(): string
    {
        if ($this->getMember() instanceof Member) {
            return $this->getMember()->getEmail();
        }

        return $this->email;
    }

    #[\Override]
    public function __toString(): string
    {
        return 'ID: '.$this->id;
    }
}
