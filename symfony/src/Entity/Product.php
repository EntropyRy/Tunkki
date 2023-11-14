<?php

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $stripeId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $stripePriceId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?int $quantity = 0;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column]
    private ?int $amount = 0;

    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Event $event = null;

    #[ORM\Column]
    private ?bool $ticket = false;

    #[ORM\Column]
    private ?bool $serviceFee = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeImageUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionFi = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionEn = null;

    #[ORM\ManyToOne]
    private ?SonataMediaMedia $picture = null;

    public function getSold(): int
    {
        if ($this->event) {
            return $this->event->getTicketTypeCount($this->getStripeId());
        }
        return 0;
    }
    public function getDescription($lang): ?string
    {
        $func = 'description' . ucfirst((string) $lang);
        return $this->{$func};
    }
    public function getMax(?int $inCheckouts): int
    {
        if ($this->event && $this->ticket) {
            $sold = $this->getSold();
            $left = $this->quantity - $sold - $inCheckouts;
            if ($left <= 10) {
                return $left;
            }
            return 10;
        }
        return 0;
    }
    public function __toString(): string
    {
        return $this->name;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getStripeId(): ?string
    {
        return $this->stripeId;
    }

    public function setStripeId(string $stripeId): static
    {
        $this->stripeId = $stripeId;

        return $this;
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

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(string $stripePriceId): static
    {
        $this->stripePriceId = $stripePriceId;

        return $this;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function isTicket(): ?bool
    {
        return $this->ticket;
    }

    public function setTicket(bool $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function isServiceFee(): ?bool
    {
        return $this->serviceFee;
    }

    public function setServiceFee(bool $serviceFee): static
    {
        $this->serviceFee = $serviceFee;

        return $this;
    }

    public function getStripeImageUrl(): ?string
    {
        return $this->stripeImageUrl;
    }

    public function setStripeImageUrl(?string $stripeImageUrl): static
    {
        $this->stripeImageUrl = $stripeImageUrl;

        return $this;
    }

    public function getDescriptionFi(): ?string
    {
        return $this->descriptionFi;
    }

    public function setDescriptionFi(?string $descriptionFi): static
    {
        $this->descriptionFi = $descriptionFi;

        return $this;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(?string $descriptionEn): static
    {
        $this->descriptionEn = $descriptionEn;

        return $this;
    }

    public function getPicture(): ?SonataMediaMedia
    {
        return $this->picture;
    }

    public function setPicture(?SonataMediaMedia $picture): static
    {
        $this->picture = $picture;

        return $this;
    }
}
