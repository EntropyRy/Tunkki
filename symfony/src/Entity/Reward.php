<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Reward implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'rewards')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\ManyToMany(targetEntity: Booking::class, inversedBy: 'rewards')]
    private Collection $bookings;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $reward = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $paid = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $PaymentHandledBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::INTEGER)]
    private int $Weight = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $Evenout = null;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings[] = $booking;
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->contains($booking)) {
            $this->bookings->removeElement($booking);
        }

        return $this;
    }

    public function getReward(): ?string
    {
        return $this->reward;
    }

    public function setReward(?string $reward): self
    {
        $this->reward = $reward;

        return $this;
    }

    public function addReward(?string $reward): self
    {
        $new = (float) $this->reward + (float) $reward;
        $this->reward = (string) $new;

        return $this;
    }

    public function getPaid(): ?bool
    {
        return $this->paid;
    }

    public function setPaid(bool $paid): self
    {
        $this->paid = $paid;

        return $this;
    }

    public function getPaidDate(): ?\DateTimeInterface
    {
        return $this->paidDate;
    }

    public function setPaidDate(?\DateTimeInterface $paidDate): self
    {
        if ($paidDate instanceof \DateTimeInterface && !$paidDate instanceof \DateTimeImmutable) {
            $paidDate = \DateTimeImmutable::createFromInterface($paidDate);
        }
        $this->paidDate = $paidDate;

        return $this;
    }

    public function getPaymentHandledBy(): ?User
    {
        return $this->PaymentHandledBy;
    }

    public function setPaymentHandledBy(?User $PaymentHandledBy): self
    {
        $this->PaymentHandledBy = $PaymentHandledBy;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        if (!$updatedAt instanceof \DateTimeImmutable) {
            $updatedAt = \DateTimeImmutable::createFromInterface($updatedAt);
        }
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        return '#'.$this->id.' for '.$this->user;
    }

    public function getWeight(): ?int
    {
        return $this->Weight;
    }

    public function setWeight(int $Weight): self
    {
        $this->Weight = $Weight;

        return $this;
    }

    public function addWeight(int $Weight): self
    {
        $this->Weight += $Weight;

        return $this;
    }

    public function getEvenout(): ?string
    {
        return $this->Evenout;
    }

    public function setEvenout(?string $Evenout): self
    {
        $this->Evenout = $Evenout;

        return $this;
    }
}
