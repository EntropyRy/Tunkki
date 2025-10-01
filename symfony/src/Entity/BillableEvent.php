<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * BillableEvent.
 */
#[ORM\Table(name: 'BillableEvent')]
#[ORM\Entity]
class BillableEvent implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: '\\'.Booking::class, inversedBy: 'billableEvents')]
    private ?Booking $booking = null;

    #[ORM\Column(name: 'description', type: 'text')]
    private string $description = '';

    #[ORM\Column(name: 'unitPrice', type: 'decimal', precision: 7, scale: 2)]
    private string $unitPrice = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
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

    #[\Override]
    public function __toString(): string
    {
        if (!in_array($this->getUnitPrice(), ['', '0'], true)) {
            return '' !== $this->description && '0' !== $this->description ? $this->description.': '.$this->getUnitPrice() : '';
        } else {
            return '';
        }
    }
}
