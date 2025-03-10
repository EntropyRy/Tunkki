<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Events
 */
#[ORM\Table(name: 'StatusEvent')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class StatusEvent implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'fixingHistory')]
    private ?Item $item = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'statusEvents')]
    private ?Booking $booking = null;

    #[ORM\Column(name: 'Description', type: Types::STRING, length: 5000, nullable: true)]
    private string $description;

    #[ORM\Column(name: 'CreatedAt', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'UpdatedAt', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $creator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $modifier = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDescription(string $description): StatusEvent
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): StatusEvent
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): StatusEvent
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setItem(?Item $item = null): StatusEvent
    {
        $this->item = $item;

        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    #[\Override]
    public function __toString(): string
    {
        if (is_object($this->getItem())) {
            return 'Event for ' . $this->getItem()->getName();
        } elseif (is_object($this->getBooking())) {
            return 'Event for ' . $this->getBooking()->getName();
        } else {
            return 'No associated item';
        }
    }

    public function setCreator(?User $creator = null): StatusEvent
    {
        $this->creator = $creator;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setModifier(?User $modifier = null): StatusEvent
    {
        $this->modifier = $modifier;

        return $this;
    }

    public function getModifier(): ?User
    {
        return $this->modifier;
    }

    public function setBooking(?Booking $booking = null): StatusEvent
    {
        $this->booking = $booking;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
}
