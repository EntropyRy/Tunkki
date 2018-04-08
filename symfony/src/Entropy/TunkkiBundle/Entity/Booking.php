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
    private $referenceNumber = 'Wait for it...';

    /**
     * @var boolean
     *
     * @ORM\Column(name="paid", type="boolean")
     */
    private $paid = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="returned", type="boolean")
     */
    private $returned = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="retrieval", type="datetime", nullable=true)
     */
    private $retrieval;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="returning", type="datetime", nullable=true)
     */
    private $returning;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="paid_date", type="datetime", nullable=true)
     */
    private $paid_date;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Item")
     */
    private $items;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Package")
     */
    private $packages;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Accessory", cascade={"persist"})
     */
    private $accessories;

    /**
     *
     * @ORM\ManyToOne(targetEntity="Entropy\TunkkiBundle\Entity\WhoCanRentChoice", cascade={"persist"})
     */
    private $rentingPrivileges;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\Entropy\TunkkiBundle\Entity\Renter", inversedBy="bookings")
     */
    private $renter;

    /**
     *
     * @ORM\OneToMany(targetEntity="\Entropy\TunkkiBundle\Entity\BillableEvent", mappedBy="booking", cascade={"persist"}, orphanRemoval=true)
     */
    private $billableEvents;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     */
    private $givenAwayBy;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     */
    private $receivedBy;

    /**
     * @ORM\Column(name="actualPrice", type="decimal", precision=7, scale=2, nullable=true)
     */
    private $actualPrice;

    /**
     * @ORM\Column(name="numberOfRentDays", type="integer")
     */
    private $numberOfRentDays = 1;

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
     * Add package
     *
     * @param \Entropy\TunkkiBundle\Entity\Package $package
     *
     * @return Booking
     */
    public function addPackage(\Entropy\TunkkiBundle\Entity\Package $package)
    {
        foreach ($package->getItems() as $item){
            $item->addRentHistory($this);
        }
        $this->packages[] = $package;

        return $this;
    }

    /**
     * Remove package
     *
     * @param \Entropy\TunkkiBundle\Entity\Package $package
     */
    public function removePackage(\Entropy\TunkkiBundle\Entity\Package $package)
    {
        foreach ($package->getItems() as $item){
            $item->removeRentHistory($this);
        }
        $this->packages->removeElement($package);
    }

    /**
     * Get packages
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPackages()
    {
        return $this->packages;
    }
    public function __toString()
    {
        return $this->name ? $this->name.' - '.date_format($this->bookingDate,'d.m.Y') : 'n/a';
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
     * Set paid
     *
     * @param boolean $paid
     *
     * @return Booking
     */
    public function setPaid($paid)
    {
        $this->setPaidDate(new \DateTime());
        $this->paid = $paid;

        return $this;
    }

    /**
     * Get paid
     *
     * @return boolean
     */
    public function getPaid()
    {
        return $this->paid;
    }

    /**
     * Set returned
     *
     * @param boolean $returned
     *
     * @return Booking
     */
    public function setReturned($returned)
    {
        $this->returned = $returned;

        return $this;
    }

    /**
     * Get returned
     *
     * @return boolean
     */
    public function getReturned()
    {
        return $this->returned;
    }

    /**
     * Set paidDate
     *
     * @param \DateTime $paidDate
     *
     * @return Booking
     */
    public function setPaidDate($paidDate)
    {
        $this->paid_date = $paidDate;

        return $this;
    }

    /**
     * Get paidDate
     *
     * @return \DateTime
     */
    public function getPaidDate()
    {
        return $this->paid_date;
    }

    /**
     * Get calculatedPrice
     *
     * @return int
     */
    public function getCalculatedTotalPrice()
    {
        $price = 0;
        foreach ($this->getItems() as $item) {
            $price += $item->getRent();
        }
        if ($this->getPackages()){
            foreach ($this->getPackages() as $package) {
                $price += $package->getRent();
            }
        }
        return $price;
    }

    /**
     * Get somethingBroken
     *
     * @return boolean
     */
    public function getIsSomethingBroken()
    {
        if ($this->getItems()){
            foreach ($this->getItems() as $item) {
                if($item->getNeedsFixing()==true){
                    return true;
                }
            }
        }
        if ($this->getPackages()){
            foreach ($this->getPackages() as $package) {
                if($package->getIsSomethingBroken()){
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Get RentNotices
     *
     * @return string
     */
    public function getRentInformation()
    {
        $return = '';
        foreach ($this->getItems() as $item) {
			if($item->getRentNotice()){
	            $return .= $item->getName().': '.$item->getRentNotice().' '. PHP_EOL;
			}
        }
        if ($this->getPackages()){
            foreach ($this->getPackages() as $package) {
                foreach ($package->getItems() as $item) {
                    $return .= $item->getName().': '.$item->getRentNotice().' ';
                }
            }
        }
        return $return;
    }
    /**
     * Set actualPrice
     *
     * @param string $actualPrice
     *
     * @return Booking
     */
    public function setActualPrice($actualPrice)
    {
        $this->actualPrice = $actualPrice;

        return $this;
    }

    /**
     * Get actualPrice
     *
     * @return string
     */
    public function getActualPrice()
    {
        return $this->actualPrice;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->items = new \Doctrine\Common\Collections\ArrayCollection();
        $this->packages = new \Doctrine\Common\Collections\ArrayCollection();
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
        $item->addRentHistory($this);
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
        $item->removeRentHistory($this);
        $this->items->removeElement($item);
    }

    /**
     * Get items
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set numberOfRentDays
     *
     * @param integer $numberOfRentDays
     *
     * @return Booking
     */
    public function setNumberOfRentDays($numberOfRentDays)
    {
        $this->numberOfRentDays = $numberOfRentDays;

        return $this;
    }

    /**
     * Get numberOfRentDays
     *
     * @return integer
     */
    public function getNumberOfRentDays()
    {
        return $this->numberOfRentDays;
    }

    /**
     * Add billableEvent
     *
     * @param \Entropy\TunkkiBundle\Entity\BillableEvent $billableEvent
     *
     * @return Booking
     */
    public function addBillableEvent(\Entropy\TunkkiBundle\Entity\BillableEvent $billableEvent)
    {
        $billableEvent->setBooking($this);
        $this->billableEvents[] = $billableEvent;

        return $this;
    }

    /**
     * Remove billableEvent
     *
     * @param \Entropy\TunkkiBundle\Entity\BillableEvent $billableEvent
     */
    public function removeBillableEvent(\Entropy\TunkkiBundle\Entity\BillableEvent $billableEvent)
    {
        $this->billableEvents->removeElement($billableEvent);
    }

    /**
     * Get billableEvents
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBillableEvents()
    {
        return $this->billableEvents;
    }


    /**
     * Set rentingPrivileges
     *
     * @param \Entropy\TunkkiBundle\Entity\WhoCanRentChoice $rentingPrivileges
     *
     * @return Booking
     */
    public function setRentingPrivileges(\Entropy\TunkkiBundle\Entity\WhoCanRentChoice $rentingPrivileges = null)
    {
        $this->rentingPrivileges = $rentingPrivileges;

        return $this;
    }

    /**
     * Get rentingPrivileges
     *
     * @return \Entropy\TunkkiBundle\Entity\WhoCanRentChoice
     */
    public function getRentingPrivileges()
    {
        return $this->rentingPrivileges;
    }

    /**
     * Add accessory
     *
     * @param \Entropy\TunkkiBundle\Entity\Accessory $accessory
     *
     * @return Booking
     */
    public function addAccessory(\Entropy\TunkkiBundle\Entity\Accessory $accessory)
    {
        $this->accessories[] = $accessory;

        return $this;
    }

    /**
     * Remove accessory
     *
     * @param \Entropy\TunkkiBundle\Entity\Accessory $accessory
     */
    public function removeAccessory(\Entropy\TunkkiBundle\Entity\Accessory $accessory)
    {
        $this->accessories->removeElement($accessory);
    }

    /**
     * Get accessories
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAccessories()
    {
        return $this->accessories;
    }

    /**
     * Set givenAwayBy
     *
     * @param \Application\Sonata\UserBundle\Entity\User $givenAwayBy
     *
     * @return Booking
     */
    public function setGivenAwayBy(\Application\Sonata\UserBundle\Entity\User $givenAwayBy = null)
    {
        $this->givenAwayBy = $givenAwayBy;

        return $this;
    }

    /**
     * Get givenAwayBy
     *
     * @return \Application\Sonata\UserBundle\Entity\User
     */
    public function getGivenAwayBy()
    {
        return $this->givenAwayBy;
    }


    /**
     * Set receivedBy
     *
     * @param \Application\Sonata\UserBundle\Entity\User $receivedBy
     *
     * @return Booking
     */
    public function setReceivedBy(\Application\Sonata\UserBundle\Entity\User $receivedBy = null)
    {
        $this->receivedBy = $receivedBy;

        return $this;
    }

    /**
     * Get receivedBy
     *
     * @return \Application\Sonata\UserBundle\Entity\User
     */
    public function getReceivedBy()
    {
        return $this->receivedBy;
    }

    /**
     * Set renter.
     *
     * @param \Entropy\TunkkiBundle\Entity\Renter|null $renter
     *
     * @return Booking
     */
    public function setRenter(\Entropy\TunkkiBundle\Entity\Renter $renter = null)
    {
        $this->renter = $renter;

        return $this;
    }

    /**
     * Get renter.
     *
     * @return \Entropy\TunkkiBundle\Entity\Renter|null
     */
    public function getRenter()
    {
        return $this->renter;
    }
}
