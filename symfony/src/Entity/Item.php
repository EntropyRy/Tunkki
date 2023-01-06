<?php

namespace App\Entity;

use App\Entity\Sonata\SonataClassificationCategory as Category;
use App\Entity\Sonata\SonataClassificationTag as Tag;
use DateTime;
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
    private readonly int $id;

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

    #[ORM\ManyToMany(targetEntity: \App\Entity\WhoCanRentChoice::class, cascade: ['persist'])]
    private \Doctrine\Common\Collections\ArrayCollection|array $whoCanRent;

    #[ORM\ManyToOne(targetEntity: Category::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private ?\App\Entity\Sonata\SonataClassificationCategory $category = null;

    #[ORM\JoinTable(name: 'Item_tags')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    private \Doctrine\Common\Collections\ArrayCollection|array $tags;

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

    #[ORM\OneToMany(targetEntity: '\\' . \App\Entity\StatusEvent::class, mappedBy: 'item', cascade: ['all'], fetch: 'LAZY')]
    private \Doctrine\Common\Collections\ArrayCollection|array $fixingHistory;

    #[ORM\OneToMany(targetEntity: '\\' . \App\Entity\File::class, mappedBy: 'product', cascade: ['all'])]
    private \Doctrine\Common\Collections\ArrayCollection|array $files;

    #[ORM\ManyToMany(targetEntity: '\\' . \App\Entity\Booking::class, cascade: ['all'])]
    private \Doctrine\Common\Collections\ArrayCollection|array $rentHistory;

    #[ORM\Column(name: 'ForSale', type: 'boolean', nullable: true)]
    private ?bool $forSale = false;

    #[ORM\ManyToMany(targetEntity: 'Package', inversedBy: 'items')]
    private \Doctrine\Common\Collections\ArrayCollection|array|\App\Entity\Package|null $packages = null;

    #[ORM\Column(name: 'Commission', type: 'datetime', nullable: true)]
    private ?\DateTime $commission = null;

    #[ORM\Column(name: 'purchasePrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $purchasePrice;

    #[ORM\ManyToOne(targetEntity: 'User')]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?\App\Entity\User $creator = null;

    #[ORM\ManyToOne(targetEntity: 'User')]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?\App\Entity\User $modifier = null;

    #[ORM\Column(name: 'createdAt', type: 'datetime')]
    private \DateTimeInterface|\DateTimeImmutable|null $createdAt = null;

    #[ORM\Column(name: 'updatedAt', type: 'datetime')]
    private \DateTimeInterface|\DateTimeImmutable|null $updatedAt = null;

    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name): Item
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
     * Set manufacturer
     *
     * @param string $manufacturer
     */
    public function setManufacturer($manufacturer): Item
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * Get manufacturer
     *
     * @return string
     */
    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    /**
     * Set model
     *
     * @param string $model
     */
    public function setModel($model): Item
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get model
     *
     * @return string
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description): Item
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set rent
     *
     * @param float $rent
     */
    public function setRent($rent): Item
    {
        $this->rent = $rent;

        return $this;
    }

    /**
     * Get rent
     *
     * @return float
     */
    public function getRent()
    {
        return $this->rent;
    }

    /**
     * Set rentNotice
     *
     * @param string $rentNotice
     */
    public function setRentNotice($rentNotice): Item
    {
        $this->rentNotice = $rentNotice;

        return $this;
    }

    /**
     * Get rentNotice
     *
     * @return string
     */
    public function getRentNotice(): ?string
    {
        return $this->rentNotice;
    }

    /**
     * Set needsFixing
     *
     * @param boolean $needsFixing
     */
    public function setNeedsFixing($needsFixing): Item
    {
        $this->needsFixing = $needsFixing;

        return $this;
    }

    /**
     * Get needsFixing
     */
    public function getNeedsFixing(): bool
    {
        return $this->needsFixing;
    }

    /**
     * Set forSale
     *
     * @param boolean $forSale
     */
    public function setForSale($forSale): Item
    {
        $this->forSale = $forSale;

        return $this;
    }

    /**
     * Get forSale
     *
     * @return boolean
     */
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

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->fixingHistory = new \Doctrine\Common\Collections\ArrayCollection();
        $this->tags = new \Doctrine\Common\Collections\ArrayCollection();
        $this->whoCanRent = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->rentHistory = new ArrayCollection();
        $this->packages = new ArrayCollection();
    }

    public function addFixingHistory(\App\Entity\StatusEvent $fixingHistory): Item
    {
        $fixingHistory->setProduct($this);
        $this->fixingHistory[] = $fixingHistory;

        return $this;
    }

    public function removeFixingHistory(\App\Entity\StatusEvent $fixingHistory): void
    {
        $fixingHistory->setProduct(null);
        $this->fixingHistory->removeElement($fixingHistory);
    }

    public function getFixingHistory()
    {
        return $this->fixingHistory;
    }

    public function getFixingHistoryMessages($count, $endofline = null): ?string
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
     * Set serialnumber
     *
     * @param string $serialnumber
     */
    public function setSerialnumber($serialnumber): Item
    {
        $this->serialnumber = $serialnumber;

        return $this;
    }

    /**
     * Get serialnumber
     *
     * @return string
     */
    public function getSerialnumber(): ?string
    {
        return $this->serialnumber;
    }


    /**
     * Add file
     *
     */
    public function addFile(\App\Entity\File $file): Item
    {
        $file->setProduct($this);
        $this->files[] = $file;

        return $this;
    }

    /**
     * Remove file
     */
    public function removeFile(\App\Entity\File $file): void
    {
        $file->setProduct(null);
        $this->files->removeElement($file);
    }

    /**
     * Get files
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Set placeinstorage
     *
     * @param string $placeinstorage
     */
    public function setPlaceinstorage($placeinstorage): Item
    {
        $this->placeinstorage = $placeinstorage;

        return $this;
    }

    /**
     * Get placeinstorage
     *
     * @return string
     */
    public function getPlaceinstorage(): ?string
    {
        return $this->placeinstorage;
    }

    /**
     * Add tag
     *
     */
    public function addTag(Tag $tag): Item
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove tag
     */
    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    /**
     * Get tags
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set creator
     *
     */
    public function setCreator(\App\Entity\User $creator = null): Item
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator
     *
     * @return \App\Entity\User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set modifier
     *
     */
    public function setModifier(\App\Entity\User $modifier = null): Item
    {
        $this->modifier = $modifier;

        return $this;
    }

    /**
     * Get modifier
     *
     * @return \App\Entity\User
     */
    public function getModifier()
    {
        return $this->modifier;
    }


    /**
     * Add package
     *
     */
    public function addPackage(\App\Entity\Package $package): Item
    {
        $this->packages[] = $package;

        return $this;
    }

    /**
     * Remove package
     */
    public function removePackage(\App\Entity\Package $package): void
    {
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

    /**
     * Set toSpareParts
     *
     * @param boolean $toSpareParts
     */
    public function setToSpareParts($toSpareParts): Item
    {
        $this->toSpareParts = $toSpareParts;

        return $this;
    }

    /**
     * Get toSpareParts
     */
    public function getToSpareParts(): bool
    {
        return $this->toSpareParts;
    }

    /**
     * Set packages
     *
     */
    public function setPackages(\App\Entity\Package $packages = null): Item
    {
        $this->packages = $packages;

        return $this;
    }

    /**
     * Add rentHistory
     *
     */
    public function addRentHistory(\App\Entity\Booking $rentHistory): Item
    {
        $this->rentHistory[] = $rentHistory;

        return $this;
    }

    /**
     * Remove rentHistory
     */
    public function removeRentHistory(\App\Entity\Booking $rentHistory): void
    {
        $this->rentHistory->removeElement($rentHistory);
    }

    /**
     * Get rentHistory
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRentHistory()
    {
        return $this->rentHistory;
    }

    /**
     * reset rentHistory
     *
     * @return null
     */
    public function resetRentHistory(): void
    {
        foreach ($this->getRentHistory() as $rent) {
            $this->removeRentHistory($rent);
        }
    }

    /**
     * Set category
     *
     */
    public function setCategory(Category $category = null): Item
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Add whoCanRent
     *
     */
    public function addWhoCanRent(\App\Entity\WhoCanRentChoice $whoCanRent): Item
    {
        $this->whoCanRent[] = $whoCanRent;

        return $this;
    }

    /**
     * Remove whoCanRent
     */
    public function removeWhoCanRent(\App\Entity\WhoCanRentChoice $whoCanRent): void
    {
        $this->whoCanRent->removeElement($whoCanRent);
    }

    /**
     * Get whoCanRent
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getWhoCanRent()
    {
        return $this->whoCanRent;
    }

    /**
     * Set url.
     *
     * @param string|null $url
     */
    public function setUrl($url = null): Item
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Set cannotBeRented.
     *
     * @param bool $cannotBeRented
     */
    public function setCannotBeRented($cannotBeRented): Item
    {
        $this->cannotBeRented = $cannotBeRented;

        return $this;
    }

    /**
     * Get cannotBeRented.
     */
    public function getCannotBeRented(): bool
    {
        return $this->cannotBeRented;
    }

    /**
     * Set compensationPrice.
     *
     * @param string|null $compensationPrice
     */
    public function setCompensationPrice($compensationPrice = null): Item
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    /**
     * Get compensationPrice.
     *
     * @return string|null
     */
    public function getCompensationPrice()
    {
        return $this->compensationPrice;
    }

    /**
     * Set purchasePrice.
     *
     * @param string|null $purchasePrice
     */
    public function setPurchasePrice($purchasePrice = null): Item
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    /**
     * Get purchasePrice.
     *
     * @return string|null
     */
    public function getPurchasePrice()
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
