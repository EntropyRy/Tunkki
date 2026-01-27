<?php

declare(strict_types=1);

namespace App\Entity\Rental\Booking;

use App\Entity\Rental\Inventory\Item;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'booking_item_snapshot')]
#[ORM\UniqueConstraint(
    name: 'uniq_booking_item_snapshot',
    columns: ['booking_id', 'item_id'],
)]
#[ORM\Entity]
class BookingItemSnapshot implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $rent = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $compensationPrice = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'itemSnapshots')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ?Booking $booking = null,
        #[ORM\ManyToOne(targetEntity: Item::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Item $item = null
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;

        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): self
    {
        $this->item = $item;

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
        return $this->item?->getName() ?? 'n/a';
    }

    public function getManufacturer(): ?string
    {
        return $this->item?->getManufacturer();
    }

    public function getModel(): ?string
    {
        return $this->item?->getModel();
    }

    public function getSerialNumber(): ?string
    {
        return $this->item?->getSerialnumber();
    }

    public function getFiles(): ?Collection
    {
        return $this->item?->getFiles();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getName();
    }
}
