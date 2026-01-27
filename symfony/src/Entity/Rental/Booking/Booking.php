<?php

declare(strict_types=1);

namespace App\Entity\Rental\Booking;

use App\Entity\Rental\Inventory\Accessory;
use App\Entity\Rental\Inventory\Item;
use App\Entity\Rental\Inventory\Package;
use App\Entity\Rental\Inventory\WhoCanRentChoice;
use App\Entity\Rental\StatusEvent;
use App\Entity\User;
use App\Repository\Rental\Booking\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Booking aggregate (rental transaction).
 *
 * NOTE (PHPStan alignment):
 *  - Fields mapped non-nullable in Doctrine are now declared non-nullable in PHP.
 *  - Default sentinel/empty values are assigned for string and DateTimeImmutable
 *    fields; lifecycle callbacks replace them with domain values at persist time.
 *  - Removed nullable types that triggered phpstan "type mapping mismatch".
 *
 * Be mindful when adding new non-nullable fields: either initialize them here
 * or ensure they are set prior to first flush (prePersist does that for dates).
 */
#[ORM\Table('Booking')]
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    /** Human readable name for the booking (non-nullable column). */
    #[ORM\Column(name: 'name', type: Types::STRING, length: 190)]
    private string $name = '';

    #[ORM\Column(name: 'referenceNumber', type: Types::STRING, length: 190)]
    private string $referenceNumber = '';

    #[ORM\Column(name: 'renterHash', type: Types::STRING, length: 199)]
    private string $renterHash = '';

    #[ORM\Column(name: 'renterConsent', type: Types::BOOLEAN)]
    private bool $renterConsent = false;

    #[ORM\Column(name: 'itemsReturned', type: Types::BOOLEAN)]
    private bool $itemsReturned = false;

    #[ORM\Column(name: 'invoiceSent', type: Types::BOOLEAN)]
    private bool $invoiceSent = false;

    #[ORM\Column(name: 'paid', type: Types::BOOLEAN)]
    private bool $paid = false;

    #[ORM\Column(name: 'cancelled', type: Types::BOOLEAN)]
    private bool $cancelled = false;

    #[ORM\Column(name: 'retrieval', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $retrieval = null;

    #[ORM\Column(
        name: 'return_date',
        type: 'datetime_immutable',
        nullable: true,
    ),]
    private ?\DateTimeImmutable $returning = null;

    #[ORM\Column(name: 'paid_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paid_date = null;

    /** @var Collection<int, Item> */
    #[ORM\ManyToMany(targetEntity: Item::class)]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
    private Collection $items;

    /** @var Collection<int, Package> */
    #[ORM\ManyToMany(targetEntity: Package::class)]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
    private Collection $packages;

    /** @var Collection<int, BookingItemSnapshot> */
    #[ORM\OneToMany(
        targetEntity: BookingItemSnapshot::class,
        mappedBy: 'booking',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $itemSnapshots;

    /** @var Collection<int, BookingPackageSnapshot> */
    #[ORM\OneToMany(
        targetEntity: BookingPackageSnapshot::class,
        mappedBy: 'booking',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $packageSnapshots;

    /** @var Collection<int, Accessory> */
    #[ORM\ManyToMany(targetEntity: Accessory::class, cascade: ['persist'])]
    private Collection $accessories;

    #[ORM\ManyToOne(
        targetEntity: WhoCanRentChoice::class,
        cascade: ['persist'],
    ),]
    private ?WhoCanRentChoice $rentingPrivileges = null;

    #[ORM\ManyToOne(targetEntity: Renter::class, inversedBy: 'bookings')]
    #[Assert\NotBlank]
    private ?Renter $renter = null;

    /** @var Collection<int, BillableEvent> */
    #[ORM\OneToMany(
        targetEntity: BillableEvent::class,
        mappedBy: 'booking',
        cascade: ['persist'],
        orphanRemoval: true,
    ),]
    private Collection $billableEvents;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $givenAwayBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $receivedBy = null;

    /**
     * Monetary aggregate (decimal). Doctrine returns string for DECIMAL.
     */
    #[ORM\Column(
        name: 'actualPrice',
        type: 'decimal',
        precision: 7,
        scale: 2,
        nullable: true,
    ),]
    private ?string $actualPrice = null;

    #[ORM\Column(name: 'numberOfRentDays', type: Types::INTEGER)]
    private int $numberOfRentDays = 1;

    /** @var Collection<int, StatusEvent> */
    #[ORM\OneToMany(
        targetEntity: StatusEvent::class,
        mappedBy: 'booking',
        cascade: ['all'],
        fetch: 'LAZY',
    ),]
    private Collection $statusEvents;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $creator = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $modifier = null;

    #[ORM\Column(name: 'modified_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $modifiedAt;

    #[ORM\Column(name: 'booking_date', type: 'date_immutable')]
    #[Assert\NotBlank]
    private \DateTimeImmutable $bookingDate;

    /** @var Collection<int, Reward> */
    #[ORM\ManyToMany(targetEntity: Reward::class, mappedBy: 'bookings')]
    private Collection $rewards;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reasonForDiscount = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $renterSignature = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $accessoryPrice = null;

    /**
     * Optimistic lock version. Doctrine manages increments.
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 1;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->packages = new ArrayCollection();
        $this->accessories = new ArrayCollection();
        $this->billableEvents = new ArrayCollection();
        $this->statusEvents = new ArrayCollection();
        $this->rewards = new ArrayCollection();
        $this->itemSnapshots = new ArrayCollection();
        $this->packageSnapshots = new ArrayCollection();

        // Initialize non-nullable DateTimes to a sentinel; replaced in lifecycle callbacks.
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->modifiedAt = $now;
        $this->bookingDate = $now;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->modifiedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->modifiedAt = new \DateTimeImmutable();
    }

    #[\Override]
    public function __toString(): string
    {
        return '' !== $this->name
            ? $this->name.' - '.$this->bookingDate->format('d.m.Y')
            : 'n/a';
    }

    /* ------------------------ Mutators / Domain Operations ------------------------ */

    public function setPaid(bool $paid): self
    {
        $this->setPaidDate(new \DateTimeImmutable());
        $this->paid = $paid;

        return $this;
    }

    public function addPackage(Package $package): self
    {
        foreach ($package->getItems() as $item) {
            $item->addRentHistory($this);
        }
        $this->packages->add($package);
        $this->ensurePackageSnapshot($package);

        return $this;
    }

    public function removePackage(Package $package): void
    {
        foreach ($package->getItems() as $item) {
            $item->removeRentHistory($this);
        }
        $this->packages->removeElement($package);
        $this->removePackageSnapshot($package);
    }

    public function addItem(Item $item): self
    {
        $item->addRentHistory($this);
        $this->items->add($item);
        $this->ensureItemSnapshot($item);

        return $this;
    }

    public function removeItem(Item $item): void
    {
        $item->removeRentHistory($this);
        $this->items->removeElement($item);
        $this->removeItemSnapshot($item);
    }

    public function addBillableEvent(BillableEvent $billableEvent): self
    {
        $billableEvent->setBooking($this);
        $this->billableEvents->add($billableEvent);

        return $this;
    }

    public function removeBillableEvent(BillableEvent $billableEvent): void
    {
        $this->billableEvents->removeElement($billableEvent);
    }

    public function addStatusEvent(StatusEvent $statusEvent): self
    {
        $this->statusEvents->add($statusEvent);

        return $this;
    }

    public function removeStatusEvent(StatusEvent $statusEvent): bool
    {
        return $this->statusEvents->removeElement($statusEvent);
    }

    public function addAccessory(Accessory $accessory): self
    {
        if (!$this->accessories->contains($accessory)) {
            $this->accessories->add($accessory);
        }

        return $this;
    }

    public function removeAccessory(Accessory $accessory): self
    {
        $this->accessories->removeElement($accessory);

        return $this;
    }

    public function addReward(Reward $reward): self
    {
        if (!$this->rewards->contains($reward)) {
            $this->rewards->add($reward);
            $reward->addBooking($this);
        }

        return $this;
    }

    public function removeReward(Reward $reward): self
    {
        if ($this->rewards->removeElement($reward)) {
            $reward->removeBooking($this);
        }

        return $this;
    }

    /* ----------------------------- Calculations / Info ---------------------------- */

    public function getCalculatedTotalPrice(): int
    {
        $price = 0;
        if ($this->itemSnapshots->count() > 0) {
            foreach ($this->itemSnapshots as $snapshot) {
                $price += (int) $snapshot->getRent();
            }
        } else {
            foreach ($this->items as $item) {
                $price += (int) $item->getRent();
            }
        }
        if ($this->packageSnapshots->count() > 0) {
            foreach ($this->packageSnapshots as $snapshot) {
                $price += (int) $snapshot->getRent();
            }
        } else {
            foreach ($this->packages as $package) {
                $price += (int) $package->getRent();
            }
        }

        return $price;
    }

    public function getIsSomethingBroken(): bool
    {
        foreach ($this->items as $item) {
            if ($item->getNeedsFixing()) {
                return true;
            }
        }
        foreach ($this->packages as $package) {
            if ($package->getIsSomethingBroken()) {
                return true;
            }
        }

        return false;
    }

    public function getRentInformation(): string
    {
        $return = '';
        foreach ($this->items as $item) {
            if ($item->getRentNotice()) {
                $return .=
                    $item->getName().
                    ': '.
                    $item->getRentNotice().
                    ' '.
                    \PHP_EOL;
            }
        }
        foreach ($this->packages as $package) {
            foreach ($package->getItems() as $item) {
                $return .=
                    $item->getName().': '.$item->getRentNotice().' ';
            }
        }

        return $return;
    }

    public function getDataArray(): array
    {
        $rent = [
            'items' => 0,
            'packages' => 0,
            'accessories' => 0,
        ];
        $compensation = [
            'items' => 0,
            'packages' => 0,
            'accessories' => 0,
        ];
        $items = [];
        $packages = [];
        $accessories = [];
        $useItemSnapshots = $this->itemSnapshots->count() > 0;
        $usePackageSnapshots = $this->packageSnapshots->count() > 0;

        if ($useItemSnapshots) {
            foreach ($this->itemSnapshots as $snapshot) {
                $items[] = $snapshot;
                $rent['items'] += (int) $snapshot->getRent();
                $compensation['items'] += (int) $snapshot->getCompensationPrice();
            }
        } else {
            foreach ($this->items as $item) {
                $items[] = $item;
                $rent['items'] += (int) $item->getRent();
                $compensation['items'] += (int) $item->getCompensationPrice();
            }
        }
        if ($usePackageSnapshots) {
            foreach ($this->packageSnapshots as $snapshot) {
                $packages[] = $snapshot;
                $rent['packages'] += (int) $snapshot->getRent();
                $compensation['packages'] += (int) $snapshot->getCompensationPrice();
            }
        } else {
            foreach ($this->packages as $package) {
                $packages[] = $package;
                $rent['packages'] += (int) $package->getRent();
                $compensation['packages'] += (int) $package->getCompensationPrice();
            }
        }
        foreach ($this->accessories as $item) {
            $accessories[] = $item;
            if (\is_int($item->getCount())) {
                $compensation['accessories'] +=
                    (int) $item->getName()->getCompensationPrice() *
                    $item->getCount();
            }
        }

        $rent['total'] = $rent['items'] + $rent['packages'];

        return [
            'actualTotal' => $this->actualPrice,
            'name' => $this->name,
            'date' => $this->bookingDate->format('j.n.Y'),
            'items' => $items,
            'packages' => $packages,
            'accessories' => $accessories,
            'rent' => array_merge($rent, [
                'actualTotal' => $this->actualPrice,
                'accessories' => $this->accessoryPrice,
            ]),
            'compensation' => $compensation,
            'renterSignature' => $this->renterSignature,
        ];
    }

    /* ----------------------------- Getters / Setters ----------------------------- */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getReferenceNumber(): string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    public function getRenterHash(): string
    {
        return $this->renterHash;
    }

    public function setRenterHash(int|string $renterHash): self
    {
        $this->renterHash = (string) $renterHash;

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function getPaid(): bool
    {
        return $this->paid;
    }

    public function setPaidDate(?\DateTimeImmutable $paidDate): self
    {
        $this->paid_date = $paidDate;

        return $this;
    }

    public function getPaidDate(): ?\DateTimeImmutable
    {
        return $this->paid_date;
    }

    public function setActualPrice(?string $actualPrice): self
    {
        $this->actualPrice = $actualPrice;

        return $this;
    }

    public function getActualPrice(): ?string
    {
        return $this->actualPrice;
    }

    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @return Collection<int, BookingItemSnapshot>
     */
    public function getItemSnapshots(): Collection
    {
        return $this->itemSnapshots;
    }

    /**
     * @return Collection<int, BookingPackageSnapshot>
     */
    public function getPackageSnapshots(): Collection
    {
        return $this->packageSnapshots;
    }

    public function setNumberOfRentDays(int $numberOfRentDays): self
    {
        $this->numberOfRentDays = $numberOfRentDays;

        return $this;
    }

    public function getNumberOfRentDays(): int
    {
        return $this->numberOfRentDays;
    }

    public function getBillableEvents(): Collection
    {
        return $this->billableEvents;
    }

    public function setRentingPrivileges(
        ?WhoCanRentChoice $rentingPrivileges = null,
    ): self {
        $this->rentingPrivileges = $rentingPrivileges;

        return $this;
    }

    public function getRenter(): ?Renter
    {
        return $this->renter;
    }

    public function setRenter(?Renter $renter = null): self
    {
        $this->renter = $renter;

        return $this;
    }

    public function setInvoiceSent(bool $invoiceSent): self
    {
        $this->invoiceSent = $invoiceSent;

        return $this;
    }

    public function getInvoiceSent(): bool
    {
        return $this->invoiceSent;
    }

    public function setItemsReturned(bool $itemsReturned): self
    {
        $this->itemsReturned = $itemsReturned;

        return $this;
    }

    public function getItemsReturned(): bool
    {
        return $this->itemsReturned;
    }

    public function setRenterConsent(bool $renterConsent): self
    {
        $this->renterConsent = $renterConsent;

        return $this;
    }

    public function getRenterConsent(): bool
    {
        return $this->renterConsent;
    }

    public function setCancelled(bool $cancelled): self
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    public function getCancelled(): bool
    {
        return $this->cancelled;
    }

    public function getStatusEvents(): Collection
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

    public function getRetrieval(): ?\DateTimeImmutable
    {
        return $this->retrieval;
    }

    public function setRetrieval(?\DateTimeImmutable $retrieval): self
    {
        $this->retrieval = $retrieval;

        return $this;
    }

    public function getReturning(): ?\DateTimeImmutable
    {
        return $this->returning;
    }

    public function setReturning(?\DateTimeImmutable $returning): self
    {
        $this->returning = $returning;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getModifiedAt(): \DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeImmutable $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getBookingDate(): \DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): self
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getRewards(): Collection
    {
        return $this->rewards;
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

    public function setAccessoryPrice(?string $accessoryPrice): self
    {
        $this->accessoryPrice = $accessoryPrice;

        return $this;
    }

    public function getRentingPrivileges(): ?WhoCanRentChoice
    {
        return $this->rentingPrivileges;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    private function ensureItemSnapshot(Item $item): void
    {
        if ($this->findItemSnapshot($item) instanceof BookingItemSnapshot) {
            return;
        }

        $snapshot = new BookingItemSnapshot($this, $item);
        $rent = $item->getRent();
        $snapshot->setRent(null !== $rent ? (string) $rent : null);
        $comp = $item->getCompensationPrice();
        $snapshot->setCompensationPrice(null !== $comp ? (string) $comp : null);
        $this->itemSnapshots->add($snapshot);
    }

    private function removeItemSnapshot(Item $item): void
    {
        $snapshot = $this->findItemSnapshot($item);
        if ($snapshot instanceof BookingItemSnapshot) {
            $this->itemSnapshots->removeElement($snapshot);
        }
    }

    private function findItemSnapshot(Item $item): ?BookingItemSnapshot
    {
        foreach ($this->itemSnapshots as $snapshot) {
            $snapshotItem = $snapshot->getItem();
            if ($snapshotItem === $item) {
                return $snapshot;
            }
            if (null === $snapshotItem) {
                continue;
            }
            if (
                null !== $snapshotItem->getId()
                && $snapshotItem->getId() === $item->getId()
            ) {
                return $snapshot;
            }
        }

        return null;
    }

    private function ensurePackageSnapshot(Package $package): void
    {
        if ($this->findPackageSnapshot($package) instanceof BookingPackageSnapshot) {
            return;
        }

        $snapshot = new BookingPackageSnapshot($this, $package);
        $snapshot->setRent($package->getRent());
        $snapshot->setCompensationPrice($package->getCompensationPrice());
        $this->packageSnapshots->add($snapshot);
    }

    private function removePackageSnapshot(Package $package): void
    {
        $snapshot = $this->findPackageSnapshot($package);
        if ($snapshot instanceof BookingPackageSnapshot) {
            $this->packageSnapshots->removeElement($snapshot);
        }
    }

    private function findPackageSnapshot(Package $package): ?BookingPackageSnapshot
    {
        foreach ($this->packageSnapshots as $snapshot) {
            $snapshotPackage = $snapshot->getPackage();
            if ($snapshotPackage === $package) {
                return $snapshot;
            }
            if (null === $snapshotPackage) {
                continue;
            }
            if (
                null !== $snapshotPackage->getId()
                && $snapshotPackage->getId() === $package->getId()
            ) {
                return $snapshot;
            }
        }

        return null;
    }
}
