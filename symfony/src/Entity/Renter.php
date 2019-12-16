<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Renter
 *
 * @ORM\Table(name="Renter")
 * @ORM\Entity
 */
class Renter
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
     * @var string
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Booking", mappedBy="renter")
     */
    private $bookings;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=190)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="streetadress", type="string", length=190, nullable=true)
     */
    private $streetadress;

    /**
     * @var string
     *
     * @ORM\Column(name="organization", type="string", length=190, nullable=true)
     */
    private $organization;

    /**
     * @var string
     *
     * @ORM\Column(name="zipcode", type="string", length=190, nullable=true)
     */
    private $zipcode;

    /**
     * @var string
     *
     * @ORM\Column(name="city", type="string", length=190, nullable=true)
     */
    private $city;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=190, nullable=true)
     */
    private $phone;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=190, nullable=true)
     */
    private $email;

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
     * Set name
     *
     * @param string $name
     *
     * @return Renter
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set streetadress
     *
     * @param string $streetadress
     *
     * @return Renter
     */
    public function setStreetadress($streetadress)
    {
        $this->streetadress = $streetadress;

        return $this;
    }

    /**
     * Get streetadress
     *
     * @return string
     */
    public function getStreetadress()
    {
        return $this->streetadress;
    }

    /**
     * Set zipcode
     *
     * @param string $zipcode
     *
     * @return Renter
     */
    public function setZipcode($zipcode)
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    /**
     * Get zipcode
     *
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * Set city
     *
     * @param string $city
     *
     * @return Renter
     */
    public function setCity($city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set phone
     *
     * @param string $phone
     *
     * @return Renter
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Renter
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set bookings
     *
     * @param \App\Entity\Booking $bookings
     *
     * @return Renter
     */
    public function setBookings(\App\Entity\Booking $bookings = null)
    {
        $this->bookings = $bookings;

        return $this;
    }

    /**
     * Get bookings
     *
     * @return \App\Entity\Booking
     */
    public function getBookings()
    {
        return $this->bookings;
    }
    public function __toString()
    {
        $name = $this->name ? $this->name : 'N/A';
        $org = $this->organization;
        return ($org ? $this->name.' / '.$org : $name);
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->bookings = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set organization
     *
     * @param string $organization
     *
     * @return Renter
     */
    public function setOrganization($organization)
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * Get organization
     *
     * @return string
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    /**
     * Add booking
     *
     * @param \App\Entity\Booking $booking
     *
     * @return Renter
     */
    public function addBooking(\App\Entity\Booking $booking)
    {
        $this->bookings[] = $booking;

        return $this;
    }

    /**
     * Remove booking
     *
     * @param \App\Entity\Booking $booking
     */
    public function removeBooking(\App\Entity\Booking $booking)
    {
        $this->bookings->removeElement($booking);
    }
}
