<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * StatusEvent.
 *
 * Notes:
 *  - Migrated to DateTimeImmutable for temporal fields (CreatedAt / UpdatedAt).
 *  - Description column is nullable; property type adjusted to ?string for consistency.
 *  - Guarded setter/getter signatures updated accordingly.
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
    private ?string $description = null;

    #[ORM\Column(name: 'CreatedAt', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'UpdatedAt', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $creator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $modifier = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setItem(?Item $item = null): self
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
        if ($this->getItem() instanceof Item) {
            return 'Event for '.$this->getItem()->getName();
        }
        if ($this->getBooking() instanceof Booking) {
            return 'Event for '.$this->getBooking()->getName();
        }

        return 'No associated item';
    }

    public function setCreator(?User $creator = null): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setModifier(?User $modifier = null): self
    {
        $this->modifier = $modifier;

        return $this;
    }

    public function getModifier(): ?User
    {
        return $this->modifier;
    }

    public function setBooking(?Booking $booking = null): self
    {
        $this->booking = $booking;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
}
