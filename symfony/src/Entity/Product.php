<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column]
    private array $stripeData = [];

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

    #[ORM\ManyToMany(targetEntity: Event::class, inversedBy: 'products')]
    private Collection $event;

    #[ORM\Column]
    private ?int $quantity = 50;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $customAmount = null;

    #[ORM\Column]
    private ?int $amount = 0;

    public function __construct()
    {
        $this->event = new ArrayCollection();
    }
    /**
     * @return array<string,?string>
     */
    public function getLineItems(?int $quantity): array
    {
        if (is_countable($this->customAmount) && count($this->customAmount) > 0) {
            return [
                'price' => $this->stripePriceId,
                'quantity' => 1
            ];
        }
        return [
            'price' => $this->stripePriceId,
            'quantity' => $quantity,
        ];
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

    public function getStripeData(): array
    {
        return $this->stripeData;
    }

    public function setStripeData(array $stripeData): static
    {
        $this->stripeData = $stripeData;

        return $this;
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

    /**
     * @return Collection<int, Event>
     */
    public function getEvent(): Collection
    {
        return $this->event;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->event->contains($event)) {
            $this->event->add($event);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        $this->event->removeElement($event);

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

    public function getCustomAmount(): ?array
    {
        return $this->customAmount;
    }

    public function setCustomAmount(?array $customAmount): static
    {
        $this->customAmount = $customAmount;

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
}
