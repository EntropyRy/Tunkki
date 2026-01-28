<?php

declare(strict_types=1);

namespace App\Entity\Rental\Booking;

use App\Entity\Rental\Inventory\Accessory;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'booking_accessory_snapshot')]
#[ORM\UniqueConstraint(
    name: 'uniq_booking_accessory_snapshot',
    columns: ['booking_id', 'accessory_id'],
)]
#[ORM\Entity]
class BookingAccessorySnapshot implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $compensationPrice = null;

    #[ORM\Column(type: Types::STRING, length: 190, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $count = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'accessorySnapshots')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Booking $booking,
        #[ORM\ManyToOne(targetEntity: Accessory::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Accessory $accessory = null,
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

    public function getAccessory(): ?Accessory
    {
        return $this->accessory;
    }

    public function setAccessory(?Accessory $accessory): self
    {
        $this->accessory = $accessory;

        return $this;
    }

    public function getCompensationPrice(): ?int
    {
        return $this->compensationPrice;
    }

    public function setCompensationPrice(?int $compensationPrice): self
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? 'n/a';
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCount(): string
    {
        return $this->count ?? '';
    }

    public function setCount(?string $count): self
    {
        $this->count = $count;

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getName();
    }
}
