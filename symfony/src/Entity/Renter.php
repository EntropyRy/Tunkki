<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Booking;

/**
 * Renter
 */
#[ORM\Table(name: 'Renter')]
#[ORM\Entity]
class Renter implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'renter')]
    private $bookings;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 190)]
    private string $name = '';

    #[ORM\Column(name: 'streetadress', type: Types::STRING, length: 190, nullable: true)]
    private ?string $streetadress = null;

    #[ORM\Column(name: 'organization', type: Types::STRING, length: 190, nullable: true)]
    private ?string $organization = null;

    #[ORM\Column(name: 'zipcode', type: Types::STRING, length: 190, nullable: true)]
    private ?string $zipcode = null;

    #[ORM\Column(name: 'city', type: Types::STRING, length: 190, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 190, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 190, nullable: true)]
    private ?string $email = null;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set name
     *
     *
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set streetadress
     *
     * @param string $streetadress
     */
    public function setStreetadress(?string $streetadress): static
    {
        $this->streetadress = $streetadress;

        return $this;
    }

    /**
     * Get streetadress
     *
     * @return string
     */
    public function getStreetadress(): ?string
    {
        return $this->streetadress;
    }

    /**
     * Set zipcode
     *
     * @param string $zipcode
     */
    public function setZipcode(?string $zipcode): static
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    /**
     * Get zipcode
     *
     * @return string
     */
    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    /**
     * Set city
     *
     * @param string $city
     */
    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city
     *
     * @return string
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * Set phone
     *
     * @param string $phone
     */
    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * Set email
     *
     * @param string $email
     */
    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set bookings
     *
     */
    public function setBookings(?Booking $bookings = null): static
    {
        $this->bookings = $bookings;

        return $this;
    }

    /**
     * Get bookings
     *
     * @return Booking
     */
    public function getBookings()
    {
        return $this->bookings;
    }
    #[\Override]
    public function __toString(): string
    {
        return ($this->organization ? $this->name . ' / ' . $this->organization : $this->name);
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    /**
     * Set organization
     *
     * @param string $organization
     */
    public function setOrganization(?string $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    /**
     * Get organization
     *
     * @return string
     */
    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    /**
     * Add booking
     *
     */
    public function addBooking(Booking $booking): static
    {
        $this->bookings[] = $booking;

        return $this;
    }

    /**
     * Remove booking
     */
    public function removeBooking(Booking $booking): void
    {
        $this->bookings->removeElement($booking);
    }
}
