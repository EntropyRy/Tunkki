<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Booking
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Entropy\TunkkiBundle\Entity\BookingRepository")
 */
class Booking
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="referenceNumber", type="string", length=255)
     */
    private $referenceNumber = 'Coming soon';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="retrieval", type="datetime")
     */
    private $retrieval;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="returning", type="datetime")
     */
    private $returning;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Item")
     */
    private $items;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Pakage")
     */
    private $pakages;

    /**
     *
     * @ORM\OneToOne(targetEntity="\Entropy\TunkkiBundle\Entity\Invoicee", mappedBy="bookings")
     */
    private $invoicee;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     */
    private $creator;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     */
    private $modifier;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="modified_at", type="datetime")
     */
    private $modifiedAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="booking_date", type="date")
     */
    private $bookingDate;


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
     * @return Booking
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
     * Set retrieval
     *
     * @param \DateTime $retrieval
     *
     * @return Booking
     */
    public function setRetrieval($retrieval)
    {
        $this->retrieval = $retrieval;

        return $this;
    }

    /**
     * Get retrieval
     *
     * @return \DateTime
     */
    public function getRetrieval()
    {
        return $this->retrieval;
    }

    /**
     * Set returning
     *
     * @param \DateTime $returning
     *
     * @return Booking
     */
    public function setReturning($returning)
    {
        $this->returning = $returning;

        return $this;
    }

    /**
     * Get returning
     *
     * @return \DateTime
     */
    public function getReturning()
    {
        return $this->returning;
    }

    /**
     * Set items
     *
     * @param string $items
     *
     * @return Booking
     */
    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }

    /**
     * Get items
     *
     * @return string
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Booking
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set modifiedAt
     *
     * @param \DateTime $modifiedAt
     *
     * @return Booking
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    /**
     * Get modifiedAt
     *
     * @return \DateTime
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * Set bookingDate
     *
     * @param \DateTime $bookingDate
     *
     * @return Booking
     */
    public function setBookingDate($bookingDate)
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    /**
     * Get bookingDate
     *
     * @return \DateTime
     */
    public function getBookingDate()
    {
        return $this->bookingDate;
    }

    /**
     * Set creator
     *
     * @param \Application\Sonata\UserBundle\Entity\User $creator
     *
     * @return Booking
     */
    public function setCreator(\Application\Sonata\UserBundle\Entity\User $creator = null)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator
     *
     * @return \Application\Sonata\UserBundle\Entity\User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set modifier
     *
     * @param \Application\Sonata\UserBundle\Entity\User $modifier
     *
     * @return Booking
     */
    public function setModifier(\Application\Sonata\UserBundle\Entity\User $modifier = null)
    {
        $this->modifier = $modifier;

        return $this;
    }

    /**
     * Get modifier
     *
     * @return \Application\Sonata\UserBundle\Entity\User
     */
    public function getModifier()
    {
        return $this->modifier;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->items = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add item
     *
     * @param \Entropy\TunkkiBundle\Entity\Item $item
     *
     * @return Booking
     */
    public function addItem(\Entropy\TunkkiBundle\Entity\Item $item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Remove item
     *
     * @param \Entropy\TunkkiBundle\Entity\Item $item
     */
    public function removeItem(\Entropy\TunkkiBundle\Entity\Item $item)
    {
        $this->items->removeElement($item);
    }

    /**
     * Add pakage
     *
     * @param \Entropy\TunkkiBundle\Entity\Pakage $pakage
     *
     * @return Booking
     */
    public function addPakage(\Entropy\TunkkiBundle\Entity\Pakage $pakage)
    {
        $this->pakages[] = $pakage;

        return $this;
    }

    /**
     * Remove pakage
     *
     * @param \Entropy\TunkkiBundle\Entity\Pakage $pakage
     */
    public function removePakage(\Entropy\TunkkiBundle\Entity\Pakage $pakage)
    {
        $this->pakages->removeElement($pakage);
    }

    /**
     * Get pakages
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPakages()
    {
        return $this->pakages;
    }
    public function __toString()
    {
        return $this->name ? $this->name.' - '.date_format($this->bookingDate,'d.m.Y') : 'n/a';
    }

    /**
     * Add invoicee
     *
     * @param \Entropy\TunkkiBundle\Entity\Invoicee $invoicee
     *
     * @return Booking
     */
    public function addInvoicee(\Entropy\TunkkiBundle\Entity\Invoicee $invoicee)
    {
        $this->invoicee[] = $invoicee;

        return $this;
    }

    /**
     * Remove invoicee
     *
     * @param \Entropy\TunkkiBundle\Entity\Invoicee $invoicee
     */
    public function removeInvoicee(\Entropy\TunkkiBundle\Entity\Invoicee $invoicee)
    {
        $this->invoicee->removeElement($invoicee);
    }

    /**
     * Get invoicee
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getInvoicee()
    {
        return $this->invoicee;
    }

    /**
     * Set referenceNumber
     *
     * @param string $referenceNumber
     *
     * @return Booking
     */
    public function setReferenceNumber($referenceNumber)
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    /**
     * Get referenceNumber
     *
     * @return string
     */
    public function getReferenceNumber()
    {
        return $this->referenceNumber;
    }

    /**
     * Set invoicee
     *
     * @param \Entropy\TunkkiBundle\Entity\Invoicee $invoicee
     *
     * @return Booking
     */
    public function setInvoicee(\Entropy\TunkkiBundle\Entity\Invoicee $invoicee = null)
    {
        $this->invoicee = $invoicee;

        return $this;
    }
}
