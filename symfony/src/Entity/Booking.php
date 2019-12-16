<?php

namespace App\Entity;

use App\Application\Sonata\UserBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Booking
 *
 * @ORM\Table("Booking")
 * @ORM\Entity(repositoryClass="App\Repository\BookingRepository")
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
     * @ORM\Column(name="name", type="string", length=190)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="referenceNumber", type="string", length=190)
     */
    private $referenceNumber = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="renterHash", type="string", length=199)
     */
    private $renterHash = 0;

    /**
     * @var boolean
     *
     * @ORM\Column(name="renterConsent", type="boolean")
     */
    private $renterConsent = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="itemsReturned", type="boolean")
     */
    private $itemsReturned = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="invoiceSent", type="boolean")
     */
    private $invoiceSent = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="paid", type="boolean")
     */
    private $paid = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="cancelled", type="boolean")
     */
    private $cancelled = false;

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
     * @ORM\ManyToMany(targetEntity="\App\Entity\Item")
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     */
    private $items;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\App\Entity\Package")
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     */
    private $packages;

    /**
     *
     * @ORM\ManyToMany(targetEntity="\App\Entity\Accessory", cascade={"persist"})
     */
    private $accessories;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\App\Entity\WhoCanRentChoice", cascade={"persist"})
     */
    private $rentingPrivileges;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\App\Entity\Renter", inversedBy="bookings")
     */
    private $renter;

    /**
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\BillableEvent", mappedBy="booking", cascade={"persist"}, orphanRemoval=true)
     */
    private $billableEvents;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\App\Application\Sonata\UserBundle\Entity\User")
     */
    private $givenAwayBy;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\App\Application\Sonata\UserBundle\Entity\User")
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
     * @ORM\OneToMany(targetEntity="\App\Entity\Event", mappedBy="booking", cascade={"all"})
     */
    private $statusEvents;

    /**
     *
     * @ORM\ManyToOne(targetEntity="\App\Application\Sonata\UserBundle\Entity\User")
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
     * @ORM\ManyToOne(targetEntity="\App\Application\Sonata\UserBundle\Entity\User")
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
     * Add package
     *
     * @param \App\Entity\Package $package
     *
     * @return Booking
     */
    public function addPackage(\App\Entity\Package $package)
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
     * @param \App\Entity\Package $package
     */
    public function removePackage(\App\Entity\Package $package)
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
        $this->items = new ArrayCollection();
        $this->packages = new ArrayCollection();
        $this->accessories = new ArrayCollection();
        $this->billableEvents = new ArrayCollection();
        $this->statusEvents = new ArrayCollection();
    }

    /**
     * Add item
     *
     * @param \App\Entity\Item $item
     *
     * @return Booking
     */
    public function addItem(\App\Entity\Item $item)
    {
        $item->addRentHistory($this);
        $this->items[] = $item;

        return $this;
    }

    /**
     * Remove item
     *
     * @param \App\Entity\Item $item
     */
    public function removeItem(\App\Entity\Item $item)
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
     * @param \App\Entity\BillableEvent $billableEvent
     *
     * @return Booking
     */
    public function addBillableEvent(\App\Entity\BillableEvent $billableEvent)
    {
        $billableEvent->setBooking($this);
        $this->billableEvents[] = $billableEvent;

        return $this;
    }

    /**
     * Remove billableEvent
     *
     * @param \App\Entity\BillableEvent $billableEvent
     */
    public function removeBillableEvent(\App\Entity\BillableEvent $billableEvent)
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
     * @param \App\Entity\WhoCanRentChoice $rentingPrivileges
     *
     * @return Booking
     */
    public function setRentingPrivileges(\App\Entity\WhoCanRentChoice $rentingPrivileges = null)
    {
        $this->rentingPrivileges = $rentingPrivileges;

        return $this;
    }

    /**
     * Set renter.
     *
     * @param \App\Entity\Renter|null $renter
     *
     * @return Booking
     */
    public function setRenter(\App\Entity\Renter $renter = null)
    {
        $this->renter = $renter;

        return $this;
    }

    /**
     * Get renter.
     *
     * @return \App\Entity\Renter|null
     */
    public function getRenter()
    {
        return $this->renter;
    }

    /**
     * Set invoiceSent.
     *
     * @param bool $invoiceSent
     *
     * @return Booking
     */
    public function setInvoiceSent($invoiceSent)
    {
        $this->invoiceSent = $invoiceSent;

        return $this;
    }

    /**
     * Get invoiceSent.
     *
     * @return bool
     */
    public function getInvoiceSent()
    {
        return $this->invoiceSent;
    }

    /**
     * Set itemsReturned.
     *
     * @param bool $itemsReturned
     *
     * @return Booking
     */
    public function setItemsReturned($itemsReturned)
    {
        $this->itemsReturned = $itemsReturned;

        return $this;
    }

    /**
     * Get itemsReturned.
     *
     * @return bool
     */
    public function getItemsReturned()
    {
        return $this->itemsReturned;
    }

    /**
     * Set renterHash.
     *
     * @param string $renterHash
     *
     * @return Booking
     */
    public function setRenterHash($renterHash)
    {
        $this->renterHash = $renterHash;

        return $this;
    }

    /**
     * Get renterHash.
     *
     * @return string
     */
    public function getRenterHash()
    {
        return $this->renterHash;
    }

    /**
     * Set renterConsent.
     *
     * @param bool $renterConsent
     *
     * @return Booking
     */
    public function setRenterConsent($renterConsent)
    {
        $this->renterConsent = $renterConsent;

        return $this;
    }

    /**
     * Get renterConsent.
     *
     * @return bool
     */
    public function getRenterConsent()
    {
        return $this->renterConsent;
    }

    /**
     * Set cancelled.
     *
     * @param bool $cancelled
     *
     * @return Booking
     */
    public function setCancelled($cancelled)
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    /**
     * Get cancelled.
     *
     * @return bool
     */
    public function getCancelled()
    {
        return $this->cancelled;
    }

    /**
     * Add statusEvent.
     *
     * @param \App\Entity\Event $statusEvent
     *
     * @return Booking
     */
    public function addStatusEvent(\App\Entity\Event $statusEvent)
    {
        $this->statusEvents[] = $statusEvent;

        return $this;
    }

    /**
     * Remove statusEvent.
     *
     * @param \App\Entity\Event $statusEvent
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeStatusEvent(\App\Entity\Event $statusEvent)
    {
        return $this->statusEvents->removeElement($statusEvent);
    }

    /**
     * Get statusEvents.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getStatusEvents()
    {
        return $this->statusEvents;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * @return Collection|Accessory[]
     */
    public function getAccessories(): Collection
    {
        return $this->accessories;
    }

    public function addAccessory(Accessory $accessory): self
    {
        if (!$this->accessories->contains($accessory)) {
            $this->accessories[] = $accessory;
        }

        return $this;
    }

    public function removeAccessory(Accessory $accessory): self
    {
        if ($this->accessories->contains($accessory)) {
            $this->accessories->removeElement($accessory);
        }

        return $this;
    }

    public function getRentingPrivileges(): ?WhoCanRentChoice
    {
        return $this->rentingPrivileges;
    }

    public function getGivenAwayBy(): ?User
    {
        return $this->givenAwayBy;
    }

    public function setGivenAwayBy(?User $givenAwayBy): self
    {
        $this->givenAwayBy = $givenAwayBy;

        return $this;
    }

    public function getReceivedBy(): ?User
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(?User $receivedBy): self
    {
        $this->receivedBy = $receivedBy;

        return $this;
    }

    public function getModifier(): ?User
    {
        return $this->modifier;
    }

    public function setModifier(?User $modifier): self
    {
        $this->modifier = $modifier;

        return $this;
    }
}
