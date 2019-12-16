<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BillableEvent
 *
 * @ORM\Table(name="BillableEvent")
 * @ORM\Entity
 */
class BillableEvent
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\App\Entity\Booking", inversedBy="billableEvents")
     */
    private $booking;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text")
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="unitPrice", type="decimal", precision=7, scale=2)
     */
    private $unitPrice;


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
     * @param \App\Entity\Booking $booking
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

    public function __toString()
    {
        if (!empty($this->getUnitPrice())){
            return $this->description ? $this->description.': '.$this->getUnitPrice() : '';
        } else {
            return '';
        }
    }
}
