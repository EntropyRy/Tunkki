<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
class Reward implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: 'User', inversedBy: 'rewards')]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\ManyToMany(targetEntity: \App\Entity\Booking::class, inversedBy: 'rewards')]
    private $bookings;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $reward;

    #[ORM\Column(type: 'boolean')]
    private bool $paid = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $paidDate;

    #[ORM\ManyToOne(targetEntity: 'User')]
    private $PaymentHandledBy;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(type: 'datetime')]
    private $updatedAt;

    #[ORM\Column(type: 'integer')]
    private int $Weight = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $Evenout;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection|Booking[]
     */
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
        $this->reward += $reward;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
    public function __toString(): string
    {
        return '#'.$this->id. ' for '.$this->user;
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
