<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table('Booking')]
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 190)]
    private ?string $name = null;

    #[ORM\Column(name: 'referenceNumber', type: Types::STRING, length: 190)]
    private int|string $referenceNumber = 0;

    #[ORM\Column(name: 'renterHash', type: Types::STRING, length: 199)]
    private int|string $renterHash = 0;

    #[ORM\Column(name: 'renterConsent', type: 'boolean')]
    private bool $renterConsent = false;

    #[ORM\Column(name: 'itemsReturned', type: 'boolean')]
    private bool $itemsReturned = false;

    #[ORM\Column(name: 'invoiceSent', type: 'boolean')]
    private bool $invoiceSent = false;

    #[ORM\Column(name: 'paid', type: 'boolean')]
    private bool $paid = false;

    #[ORM\Column(name: 'cancelled', type: 'boolean')]
    private bool $cancelled = false;

    #[ORM\Column(name: 'retrieval', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $retrieval = null;

    #[ORM\Column(name: 'return_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $returning = null;

    #[ORM\Column(name: 'paid_date', type: 'datetime', nullable: true)]
    private ?\DateTime $paid_date = null;

    #[ORM\ManyToMany(targetEntity: Item::class)]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
    private $items;

    #[ORM\ManyToMany(targetEntity: Package::class)]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
    private $packages;

    #[ORM\ManyToMany(targetEntity: Accessory::class, cascade: ['persist'])]
    private $accessories;

    #[ORM\ManyToOne(targetEntity: WhoCanRentChoice::class, cascade: ['persist'])]
    private ?WhoCanRentChoice $rentingPrivileges = null;

    #[ORM\ManyToOne(targetEntity: Renter::class, inversedBy: 'bookings')]
    #[Assert\NotBlank]
    private ?Renter $renter = null;

    #[ORM\OneToMany(targetEntity: BillableEvent::class, mappedBy: 'booking', cascade: ['persist'], orphanRemoval: true)]
    private $billableEvents;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $givenAwayBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $receivedBy = null;

    #[ORM\Column(name: 'actualPrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $actualPrice;

    #[ORM\Column(name: 'numberOfRentDays', type: 'integer')]
    private int $numberOfRentDays = 1;

    #[ORM\OneToMany(targetEntity: StatusEvent::class, mappedBy: 'booking', cascade: ['all'], fetch: 'LAZY')]
    private $statusEvents;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $creator = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $modifier = null;

    #[ORM\Column(name: 'modified_at', type: 'datetime')]
    private ?\DateTimeInterface $modifiedAt = null;

    #[ORM\Column(name: 'booking_date', type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $bookingDate = null;

    #[ORM\ManyToMany(targetEntity: Reward::class, mappedBy: 'bookings')]
    private $rewards;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reasonForDiscount = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $renterSignature = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $accessoryPrice = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    // @phpstan-ignore-next-line
    private ?int $version = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->modifiedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->modifiedAt = new \DateTime();
    }

    public function addPackage(Package $package): Booking
    {
        foreach ($package->getItems() as $item) {
            $item->addRentHistory($this);
        }
        $this->packages[] = $package;

        return $this;
    }

    public function removePackage(Package $package): void
    {
        foreach ($package->getItems() as $item) {
            $item->removeRentHistory($this);
        }
        $this->packages->removeElement($package);
    }

    public function getPackages()
    {
        return $this->packages;
    }
    #[\Override]
    public function __toString(): string
    {
        return $this->name ? $this->name . ' - ' . date_format($this->bookingDate, 'd.m.Y') : 'n/a';
    }

    public function setPaid($paid): Booking
    {
        $this->setPaidDate(new \DateTime());
        $this->paid = $paid;

        return $this;
    }

    public function getPaid(): bool
    {
        return $this->paid;
    }

    public function setPaidDate($paidDate): Booking
    {
        $this->paid_date = $paidDate;

        return $this;
    }

    public function getPaidDate(): ?\DateTime
    {
        return $this->paid_date;
    }

    public function getCalculatedTotalPrice(): int
    {
        $price = 0;
        foreach ($this->getItems() as $item) {
            $price += $item->getRent();
        }
        if ($this->getPackages()) {
            foreach ($this->getPackages() as $package) {
                $price += $package->getRent();
            }
        }
        return $price;
    }

    public function getIsSomethingBroken(): bool
    {
        if ($this->getItems()) {
            foreach ($this->getItems() as $item) {
                if ($item->getNeedsFixing() == true) {
                    return true;
                }
            }
        }
        if ($this->getPackages()) {
            foreach ($this->getPackages() as $package) {
                if ($package->getIsSomethingBroken()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getRentInformation(): string
    {
        $return = '';
        foreach ($this->getItems() as $item) {
            if ($item->getRentNotice()) {
                $return .= $item->getName() . ': ' . $item->getRentNotice() . ' ' . PHP_EOL;
            }
        }
        if ($this->getPackages()) {
            foreach ($this->getPackages() as $package) {
                foreach ($package->getItems() as $item) {
                    $return .= $item->getName() . ': ' . $item->getRentNotice() . ' ';
                }
            }
        }
        return $return;
    }

    public function setActualPrice($actualPrice): Booking
    {
        $this->actualPrice = $actualPrice;

        return $this;
    }

    public function getActualPrice()
    {
        return $this->actualPrice;
    }

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->packages = new ArrayCollection();
        $this->accessories = new ArrayCollection();
        $this->billableEvents = new ArrayCollection();
        $this->statusEvents = new ArrayCollection();
        $this->rewards = new ArrayCollection();
    }

    public function addItem(Item $item): Booking
    {
        $item->addRentHistory($this);
        $this->items[] = $item;

        return $this;
    }

    public function removeItem(Item $item): void
    {
        $item->removeRentHistory($this);
        $this->items->removeElement($item);
    }

    public function getItems()
    {
        return $this->items;
    }

    public function setNumberOfRentDays($numberOfRentDays): Booking
    {
        $this->numberOfRentDays = $numberOfRentDays;

        return $this;
    }

    public function getNumberOfRentDays(): int
    {
        return $this->numberOfRentDays;
    }

    public function addBillableEvent(BillableEvent $billableEvent): Booking
    {
        $billableEvent->setBooking($this);
        $this->billableEvents[] = $billableEvent;

        return $this;
    }

    public function removeBillableEvent(BillableEvent $billableEvent): void
    {
        $this->billableEvents->removeElement($billableEvent);
    }

    public function getBillableEvents()
    {
        return $this->billableEvents;
    }

    public function setRentingPrivileges(?WhoCanRentChoice $rentingPrivileges = null): Booking
    {
        $this->rentingPrivileges = $rentingPrivileges;

        return $this;
    }

    public function setRenter(?Renter $renter = null): Booking
    {
        $this->renter = $renter;

        return $this;
    }

    public function getRenter(): ?Renter
    {
        return $this->renter;
    }

    public function setInvoiceSent($invoiceSent): Booking
    {
        $this->invoiceSent = $invoiceSent;

        return $this;
    }

    public function getInvoiceSent(): bool
    {
        return $this->invoiceSent;
    }

    public function setItemsReturned($itemsReturned): Booking
    {
        $this->itemsReturned = $itemsReturned;

        return $this;
    }

    public function getItemsReturned(): bool
    {
        return $this->itemsReturned;
    }

    public function setRenterHash($renterHash): Booking
    {
        $this->renterHash = $renterHash;

        return $this;
    }

    public function getRenterHash(): int|string
    {
        return $this->renterHash;
    }

    public function setRenterConsent($renterConsent): Booking
    {
        $this->renterConsent = $renterConsent;

        return $this;
    }

    public function getRenterConsent(): bool
    {
        return $this->renterConsent;
    }

    public function setCancelled($cancelled): Booking
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    public function getCancelled(): bool
    {
        return $this->cancelled;
    }

    public function addStatusEvent(StatusEvent $statusEvent): Booking
    {
        $this->statusEvents[] = $statusEvent;

        return $this;
    }

    public function removeStatusEvent(StatusEvent $statusEvent): bool
    {
        return $this->statusEvents->removeElement($statusEvent);
    }

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    public function getRetrieval(): ?\DateTimeInterface
    {
        return $this->retrieval;
    }

    public function setRetrieval(?\DateTimeInterface $retrieval): self
    {
        $this->retrieval = $retrieval;

        return $this;
    }

    public function getReturning(): ?\DateTimeInterface
    {
        return $this->returning;
    }

    public function setReturning(?\DateTimeInterface $returning): self
    {
        $this->returning = $returning;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getModifiedAt(): ?\DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeInterface $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getBookingDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeInterface $bookingDate): self
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getRewards(): Collection
    {
        return $this->rewards;
    }

    public function addReward(Reward $reward): self
    {
        if (!$this->rewards->contains($reward)) {
            $this->rewards[] = $reward;
            $reward->addBooking($this);
        }

        return $this;
    }

    public function removeReward(Reward $reward): self
    {
        if ($this->rewards->contains($reward)) {
            $this->rewards->removeElement($reward);
            $reward->removeBooking($this);
        }

        return $this;
    }

    public function getReasonForDiscount(): ?string
    {
        return $this->reasonForDiscount;
    }

    public function setReasonForDiscount(?string $reasonForDiscount): self
    {
        $this->reasonForDiscount = $reasonForDiscount;

        return $this;
    }

    public function getRenterSignature(): ?string
    {
        return $this->renterSignature;
    }

    public function setRenterSignature(?string $renterSignature): self
    {
        $this->renterSignature = $renterSignature;

        return $this;
    }

    public function getAccessoryPrice(): ?string
    {
        return $this->accessoryPrice;
    }

    public function setAccessoryPrice(?string $accessoryPrice): static
    {
        $this->accessoryPrice = $accessoryPrice;

        return $this;
    }
    public function getDataArray(): array
    {
        $object = $this;
        $rent = [];
        $compensation = [];
        $data = [];
        $items = [];
        $packages = [];
        $accessories = [];
        $rent['items'] = 0;
        $compensation['items'] = 0;
        $rent['packages'] = 0;
        $compensation['packages'] = 0;
        $rent['accessories'] = 0;
        $compensation['accessories'] = 0;
        foreach ($object->getItems() as $item) {
            $items[] = $item;
            $rent['items'] += $item->getRent();
            $compensation['items'] += $item->getCompensationPrice();
        }
        foreach ($object->getPackages() as $item) {
            $packages[] = $item;
            $rent['packages'] += $item->getRent();
            $compensation['packages'] += $item->getCompensationPrice();
        }
        foreach ($object->getAccessories() as $item) {
            $accessories[] = $item;
            if (is_int($item->getCount())) {
                $compensation['accessories'] += $item->getName()->getCompensationPrice() * $item->getCount();
            }
        }
        $rent['total'] = $rent['items'] + $rent['packages']; //+ $rent['accessories'];

        $data['actualTotal'] = $object->getActualPrice();
        $rent['actualTotal'] = $object->getActualPrice();
        $rent['accessories'] = $object->getAccessoryPrice();
        $data['name'] = $object->getName();
        $data['date'] = $object->getBookingDate()->format('j.n.Y');
        $data['items'] = $items;
        $data['packages'] = $packages;
        $data['accessories'] = $accessories;
        $data['rent'] = $rent;
        $data['compensation'] = $compensation;
        $data['renterSignature'] = $object->getRenterSignature();
        return $data;
    }
}
