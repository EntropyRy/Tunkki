<?php

declare(strict_types=1);

namespace App\Entity\Rental\Inventory;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\StatusEvent;
use App\Entity\Sonata\SonataClassificationCategory as Category;
use App\Entity\Sonata\SonataClassificationTag as Tag;
use App\Entity\User;
use App\Repository\Rental\Inventory\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * item.
 */
#[ORM\Table(name: 'Item')]
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Item implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'Name', type: Types::STRING, length: 190)]
    private string $name = '';

    #[ORM\Column(name: 'Manufacturer', type: Types::STRING, length: 190, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(name: 'Model', type: Types::STRING, length: 190, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'Url', type: Types::STRING, length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(name: 'SerialNumber', type: Types::STRING, length: 190, nullable: true)]
    private ?string $serialnumber = null;

    #[ORM\Column(name: 'PlaceInStorage', type: Types::STRING, length: 190, nullable: true)]
    private ?string $placeinstorage = null;

    #[ORM\Column(name: 'Description', type: Types::STRING, length: 4000, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, WhoCanRentChoice>
     */
    #[ORM\ManyToMany(targetEntity: WhoCanRentChoice::class, cascade: ['persist'])]
    private Collection $whoCanRent;

    #[ORM\ManyToOne(targetEntity: Category::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private ?Category $category = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\JoinTable(name: 'Item_tags')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    private Collection $tags;

    #[ORM\Column(name: 'Rent', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $rent;

    #[ORM\Column(name: 'compensationPrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $compensationPrice;

    #[ORM\Column(name: 'RentNotice', type: Types::STRING, length: 5000, nullable: true)]
    private ?string $rentNotice = null;

    #[ORM\Column(name: 'NeedsFixing', type: Types::BOOLEAN)]
    private bool $needsFixing = false;

    #[ORM\Column(name: 'ToSpareParts', type: Types::BOOLEAN)]
    private bool $toSpareParts = false;

    #[ORM\Column(name: 'CannotBeRented', type: Types::BOOLEAN)]
    private bool $cannotBeRented = false;

    /**
     * @var Collection<int, StatusEvent>
     */
    #[ORM\OneToMany(targetEntity: StatusEvent::class, mappedBy: 'item', cascade: ['all'], fetch: 'LAZY')]
    private Collection $fixingHistory;

    /**
     * @var Collection<int, File>
     */
    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'product', cascade: ['all'])]
    private Collection $files;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\ManyToMany(targetEntity: Booking::class, cascade: ['all'])]
    private Collection $rentHistory;

    #[ORM\Column(name: 'ForSale', type: Types::BOOLEAN, nullable: true)]
    private ?bool $forSale = false;

    /**
     * @var Collection<int, Package>
     */
    #[ORM\ManyToMany(targetEntity: Package::class, inversedBy: 'items')]
    private Collection $packages;

    #[ORM\Column(name: 'Commission', type: 'datetime', nullable: true)]
    private ?\DateTime $commission = null;

    #[ORM\Column(name: 'purchasePrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $purchasePrice;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $creator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $modifier = null;

    #[ORM\Column(name: 'createdAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setManufacturer(string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setRent($rent): self
    {
        $this->rent = $rent;

        return $this;
    }

    public function getRent()
    {
        return $this->rent;
    }

    public function setRentNotice(mixed $rentNotice): self
    {
        $this->rentNotice = $rentNotice;

        return $this;
    }

    public function getRentNotice(): ?string
    {
        return $this->rentNotice;
    }

    public function setNeedsFixing(mixed $needsFixing): self
    {
        $this->needsFixing = $needsFixing;

        return $this;
    }

    public function getNeedsFixing(): bool
    {
        return $this->needsFixing;
    }

    public function setForSale(mixed $forSale): self
    {
        $this->forSale = $forSale;

        return $this;
    }

    public function getForSale(): ?bool
    {
        return $this->forSale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        if ('' !== $this->name && '0' !== $this->name) {
            return $this->name;
        } else {
            return 'N/A';
        }
    }

    public function __construct()
    {
        $this->fixingHistory = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->whoCanRent = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->rentHistory = new ArrayCollection();
        $this->packages = new ArrayCollection();
    }

    public function addFixingHistory(StatusEvent $fixingHistory): self
    {
        $fixingHistory->setItem($this);
        $this->fixingHistory[] = $fixingHistory;

        return $this;
    }

    public function removeFixingHistory(StatusEvent $fixingHistory): void
    {
        $fixingHistory->setItem();
        $this->fixingHistory->removeElement($fixingHistory);
    }

    public function getFixingHistory(): Collection
    {
        return $this->fixingHistory;
    }

    public function getFixingHistoryMessages(int $count, ?string $endofline = null): ?string
    {
        $eol = 'html' == $endofline ? '<br>' : \PHP_EOL;
        $messages = '';
        foreach (\array_slice(array_reverse($this->getFixingHistory()->toArray()), 0, $count) as $event) {
            $user = $event->getCreator() ? $event->getCreator()->getUsername() : 'n/a';
            $messages .= '['.$event->getCreatedAt()->format('j.n.Y H:m').'] '.$user.': '.$event->getDescription().''.$eol;
        }
        if (null != $messages) {
            return $messages;
        } else {
            return 'no messages';
        }
    }

    public function resetFixingHistory(): void
    {
        foreach ($this->getFixingHistory() as $fix) {
            $this->removeFixingHistory($fix);
        }
    }

    public function resetWhoCanRent(): void
    {
        foreach ($this->getWhoCanRent() as $who) {
            $this->removeWhoCanRent($who);
        }
    }

    public function setCommission(mixed $commission): self
    {
        $this->commission = $commission;

        return $this;
    }

    public function getCommission(): ?\DateTime
    {
        return $this->commission;
    }

    public function setSerialnumber(mixed $serialnumber): self
    {
        $this->serialnumber = $serialnumber;

        return $this;
    }

    public function getSerialnumber(): ?string
    {
        return $this->serialnumber;
    }

    public function addFile(File $file): self
    {
        $file->setProduct($this);
        $this->files[] = $file;

        return $this;
    }

    public function removeFile(File $file): void
    {
        $file->setProduct();
        $this->files->removeElement($file);
    }

    public function getFiles(): ?Collection
    {
        return $this->files;
    }

    public function setPlaceinstorage(mixed $placeinstorage): self
    {
        $this->placeinstorage = $placeinstorage;

        return $this;
    }

    public function getPlaceinstorage(): ?string
    {
        return $this->placeinstorage;
    }

    public function addTag(Tag $tag): self
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function setCreator(?User $creator = null): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setModifier(?User $modifier = null): self
    {
        $this->modifier = $modifier;

        return $this;
    }

    public function getModifier(): ?User
    {
        return $this->modifier;
    }

    public function addPackage(Package $package): self
    {
        $this->packages[] = $package;

        return $this;
    }

    public function removePackage(Package $package): void
    {
        $this->packages->removeElement($package);
    }

    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function setToSpareParts(mixed $toSpareParts): self
    {
        $this->toSpareParts = $toSpareParts;

        return $this;
    }

    public function getToSpareParts(): bool
    {
        return $this->toSpareParts;
    }

    public function setPackages(?Package $packages = null): self
    {
        $this->packages = $packages;

        return $this;
    }

    public function addRentHistory(Booking $rentHistory): self
    {
        $this->rentHistory[] = $rentHistory;

        return $this;
    }

    public function removeRentHistory(Booking $rentHistory): void
    {
        $this->rentHistory->removeElement($rentHistory);
    }

    public function getRentHistory(): Collection
    {
        return $this->rentHistory;
    }

    public function resetRentHistory(): void
    {
        foreach ($this->getRentHistory() as $rent) {
            $this->removeRentHistory($rent);
        }
    }

    public function setCategory(?Category $category = null): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function addWhoCanRent(WhoCanRentChoice $whoCanRent): self
    {
        $this->whoCanRent[] = $whoCanRent;

        return $this;
    }

    public function removeWhoCanRent(WhoCanRentChoice $whoCanRent): void
    {
        $this->whoCanRent->removeElement($whoCanRent);
    }

    public function getWhoCanRent(): Collection
    {
        return $this->whoCanRent;
    }

    public function setUrl(mixed $url = null): self
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setCannotBeRented(mixed $cannotBeRented): self
    {
        $this->cannotBeRented = $cannotBeRented;

        return $this;
    }

    public function getCannotBeRented(): bool
    {
        return $this->cannotBeRented;
    }

    public function setCompensationPrice(mixed $compensationPrice = null): self
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    public function getCompensationPrice()
    {
        return $this->compensationPrice;
    }

    public function setPurchasePrice(mixed $purchasePrice = null): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
