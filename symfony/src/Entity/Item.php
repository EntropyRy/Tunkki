<?php

namespace App\Entity;

use App\Entity\Sonata\SonataClassificationCategory as Category;
use App\Entity\Sonata\SonataClassificationTag as Tag;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * item
 */
#[ORM\Table(name: 'Item')]
#[ORM\Entity(repositoryClass: \App\Repository\ItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Item implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'Name', type: 'string', length: 190)]
    private string $name;

    #[ORM\Column(name: 'Manufacturer', type: 'string', length: 190, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(name: 'Model', type: 'string', length: 190, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'Url', type: 'string', length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(name: 'SerialNumber', type: 'string', length: 190, nullable: true)]
    private ?string $serialnumber = null;

    #[ORM\Column(name: 'PlaceInStorage', type: 'string', length: 190, nullable: true)]
    private ?string $placeinstorage = null;

    #[ORM\Column(name: 'Description', type: 'string', length: 4000, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToMany(targetEntity: WhoCanRentChoice::class, cascade: ['persist'])]
    private $whoCanRent;

    #[ORM\ManyToOne(targetEntity: Category::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private ?Category $category = null;

    #[ORM\JoinTable(name: 'Item_tags')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    private $tags;

    #[ORM\Column(name: 'Rent', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $rent;

    #[ORM\Column(name: 'compensationPrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $compensationPrice;

    #[ORM\Column(name: 'RentNotice', type: 'string', length: 5000, nullable: true)]
    private ?string $rentNotice = null;

    #[ORM\Column(name: 'NeedsFixing', type: 'boolean')]
    private bool $needsFixing = false;

    #[ORM\Column(name: 'ToSpareParts', type: 'boolean')]
    private bool $toSpareParts = false;

    #[ORM\Column(name: 'CannotBeRented', type: 'boolean')]
    private bool $cannotBeRented = false;

    #[ORM\OneToMany(targetEntity: StatusEvent::class, mappedBy: 'item', cascade: ['all'], fetch: 'LAZY')]
    private $fixingHistory;

    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'product', cascade: ['all'])]
    private $files;

    #[ORM\ManyToMany(targetEntity: Booking::class, cascade: ['all'])]
    private $rentHistory;

    #[ORM\Column(name: 'ForSale', type: 'boolean', nullable: true)]
    private ?bool $forSale = false;

    #[ORM\ManyToMany(targetEntity: Package::class, inversedBy: 'items')]
    private $packages = null;

    #[ORM\Column(name: 'Commission', type: 'datetime', nullable: true)]
    private ?\DateTime $commission = null;

    #[ORM\Column(name: 'purchasePrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $purchasePrice;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?\App\Entity\User $creator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?\App\Entity\User $modifier = null;

    #[ORM\Column(name: 'createdAt', type: 'datetime')]
    private \DateTimeInterface|\DateTimeImmutable|null $createdAt = null;

    #[ORM\Column(name: 'updatedAt', type: 'datetime')]
    private \DateTimeInterface|\DateTimeImmutable|null $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): Item
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setManufacturer(string $manufacturer): Item
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setModel(string $model): Item
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setDescription(string $description): Item
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setRent($rent): Item
    {
        $this->rent = $rent;

        return $this;
    }

    public function getRent()
    {
        return $this->rent;
    }
    /**
     * @param mixed $rentNotice
     */
    public function setRentNotice($rentNotice): Item
    {
        $this->rentNotice = $rentNotice;

        return $this;
    }

    public function getRentNotice(): ?string
    {
        return $this->rentNotice;
    }
    /**
     * @param mixed $needsFixing
     */
    public function setNeedsFixing($needsFixing): Item
    {
        $this->needsFixing = $needsFixing;

        return $this;
    }

    public function getNeedsFixing(): bool
    {
        return $this->needsFixing;
    }
    /**
     * @param mixed $forSale
     */
    public function setForSale($forSale): Item
    {
        $this->forSale = $forSale;

        return $this;
    }

    public function getForSale(): ?bool
    {
        return $this->forSale;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function __toString(): string
    {
        if ($this->name) {
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

    public function addFixingHistory(\App\Entity\StatusEvent $fixingHistory): Item
    {
        $fixingHistory->setItem($this);
        $this->fixingHistory[] = $fixingHistory;

        return $this;
    }

    public function removeFixingHistory(\App\Entity\StatusEvent $fixingHistory): void
    {
        $fixingHistory->setItem(null);
        $this->fixingHistory->removeElement($fixingHistory);
    }

    public function getFixingHistory()
    {
        return $this->fixingHistory;
    }

    public function getFixingHistoryMessages(int $count, ?string $endofline = null): ?string
    {
        if ($endofline == 'html') {
            $eol = "<br>";
        } else {
            $eol = PHP_EOL;
        }
        $messages = '';
        foreach (array_slice(array_reverse($this->getFixingHistory()->toArray()), 0, $count) as $event) {
            $user = $event->getCreator() ? $event->getCreator()->getUsername() : 'n/a';
            $messages .= '[' . $event->getCreatedAt()->format('j.n.Y H:m') . '] ' . $user . ': ' . $event->getDescription() . '' . $eol;
        }
        if ($messages != null) {
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
    /**
     * @param mixed $commission
     */
    public function setCommission($commission): Item
    {
        $this->commission = $commission;

        return $this;
    }

    public function getCommission(): ?DateTime
    {
        return $this->commission;
    }
    /**
     * @param mixed $serialnumber
     */
    public function setSerialnumber($serialnumber): Item
    {
        $this->serialnumber = $serialnumber;

        return $this;
    }

    public function getSerialnumber(): ?string
    {
        return $this->serialnumber;
    }

    public function addFile(\App\Entity\File $file): Item
    {
        $file->setProduct($this);
        $this->files[] = $file;

        return $this;
    }

    public function removeFile(\App\Entity\File $file): void
    {
        $file->setProduct(null);
        $this->files->removeElement($file);
    }

    public function getFiles(): ?Collection
    {
        return $this->files;
    }
    /**
     * @param mixed $placeinstorage
     */
    public function setPlaceinstorage($placeinstorage): Item
    {
        $this->placeinstorage = $placeinstorage;

        return $this;
    }

    public function getPlaceinstorage(): ?string
    {
        return $this->placeinstorage;
    }

    public function addTag(Tag $tag): Item
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

    public function setCreator(\App\Entity\User $creator = null): Item
    {
        $this->creator = $creator;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setModifier(\App\Entity\User $modifier = null): Item
    {
        $this->modifier = $modifier;

        return $this;
    }

    public function getModifier(): ?User
    {
        return $this->modifier;
    }

    public function addPackage(\App\Entity\Package $package): Item
    {
        $this->packages[] = $package;

        return $this;
    }

    public function removePackage(\App\Entity\Package $package): void
    {
        $this->packages->removeElement($package);
    }

    public function getPackages(): Collection
    {
        return $this->packages;
    }
    /**
     * @param mixed $toSpareParts
     */
    public function setToSpareParts($toSpareParts): Item
    {
        $this->toSpareParts = $toSpareParts;

        return $this;
    }

    public function getToSpareParts(): bool
    {
        return $this->toSpareParts;
    }

    public function setPackages(\App\Entity\Package $packages = null): Item
    {
        $this->packages = $packages;

        return $this;
    }

    public function addRentHistory(\App\Entity\Booking $rentHistory): Item
    {
        $this->rentHistory[] = $rentHistory;

        return $this;
    }

    public function removeRentHistory(\App\Entity\Booking $rentHistory): void
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

    public function setCategory(Category $category = null): Item
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function addWhoCanRent(\App\Entity\WhoCanRentChoice $whoCanRent): Item
    {
        $this->whoCanRent[] = $whoCanRent;

        return $this;
    }

    public function removeWhoCanRent(\App\Entity\WhoCanRentChoice $whoCanRent): void
    {
        $this->whoCanRent->removeElement($whoCanRent);
    }

    public function getWhoCanRent(): Collection
    {
        return $this->whoCanRent;
    }
    /**
     * @param mixed $url
     */
    public function setUrl($url = null): Item
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
    /**
     * @param mixed $cannotBeRented
     */
    public function setCannotBeRented($cannotBeRented): Item
    {
        $this->cannotBeRented = $cannotBeRented;

        return $this;
    }

    public function getCannotBeRented(): bool
    {
        return $this->cannotBeRented;
    }
    /**
     * @param mixed $compensationPrice
     */
    public function setCompensationPrice($compensationPrice = null): Item
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    public function getCompensationPrice()
    {
        return $this->compensationPrice;
    }
    /**
     * @param mixed $purchasePrice
     */
    public function setPurchasePrice($purchasePrice = null): Item
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
