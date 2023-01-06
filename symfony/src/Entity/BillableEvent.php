<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BillableEvent
 */
#[ORM\Table(name: 'BillableEvent')]
#[ORM\Entity]
class BillableEvent implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private readonly int $id;

    #[ORM\ManyToOne(targetEntity: '\\' . \App\Entity\Booking::class, inversedBy: 'billableEvents')]
    private ?\App\Entity\Booking $booking = null;

    #[ORM\Column(name: 'description', type: 'text')]
    private string $description;

    #[ORM\Column(name: 'unitPrice', type: 'decimal', precision: 7, scale: 2)]
    private string $unitPrice;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return BillableEvent
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set unitPrice
     *
     * @param string $unitPrice
     *
     * @return BillableEvent
     */
    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * Get unitPrice
     *
     * @return string
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * Set booking
     *
     *
     * @return BillableEvent
     */
    public function setBooking(\App\Entity\Booking $booking = null)
    {
        $this->booking = $booking;

        return $this;
    }

    /**
     * Get booking
     *
     * @return \App\Entity\Booking
     */
    public function getBooking()
    {
        return $this->booking;
    }

    public function __toString(): string
    {
        if (!empty($this->getUnitPrice())) {
            return (string) ($this->description ? $this->description.': '.$this->getUnitPrice() : '');
        } else {
            return '';
        }
    }
}
