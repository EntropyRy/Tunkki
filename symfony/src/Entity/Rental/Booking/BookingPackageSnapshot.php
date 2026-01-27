<?php

declare(strict_types=1);

namespace App\Entity\Rental\Booking;

use App\Entity\Rental\Inventory\Package;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'booking_package_snapshot')]
#[ORM\UniqueConstraint(
    name: 'uniq_booking_package_snapshot',
    columns: ['booking_id', 'package_id'],
)]
#[ORM\Entity]
class BookingPackageSnapshot implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $rent = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $compensationPrice = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'packageSnapshots')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Booking $booking,
        #[ORM\ManyToOne(targetEntity: Package::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Package $package = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function setBooking(Booking $booking): self
    {
        $this->booking = $booking;

        return $this;
    }

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    public function setPackage(?Package $package): self
    {
        $this->package = $package;

        return $this;
    }

    public function getRent(): ?string
    {
        return $this->rent;
    }

    public function setRent(?string $rent): self
    {
        $this->rent = $rent;

        return $this;
    }

    public function getCompensationPrice(): ?string
    {
        return $this->compensationPrice;
    }

    public function setCompensationPrice(?string $compensationPrice): self
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    public function getName(): string
    {
        return $this->package?->getName() ?? 'n/a';
    }

    public function getItems(): Collection
    {
        return $this->package?->getItems() ?? new ArrayCollection();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getName();
    }
}
