<?php

namespace App\Entity;

use App\Entity\Sonata\SonataClassificationCategory as Category;
use App\Entity\Sonata\SonataClassificationTag as Tag;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Item
 */
#[ORM\Table(name: 'Item')]
#[ORM\Entity(repositoryClass: \App\Repository\ItemsRepository::class)]
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
    private string $manufacturer;

    #[ORM\Column(name: 'Model', type: 'string', length: 190, nullable: true)]
    private string $model;

    #[ORM\Column(name: 'Url', type: 'string', length: 500, nullable: true)]
    private string $url;

    #[ORM\Column(name: 'SerialNumber', type: 'string', length: 190, nullable: true)]
    private string $serialnumber;

    #[ORM\Column(name: 'PlaceInStorage', type: 'string', length: 190, nullable: true)]
    private string $placeinstorage;

    #[ORM\Column(name: 'Description', type: 'string', length: 4000, nullable: true)]
    private string $description;

    #[ORM\ManyToMany(targetEntity: \App\Entity\WhoCanRentChoice::class, cascade: ['persist'])]
    private $whoCanRent;

    #[ORM\ManyToOne(targetEntity: Category::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private $category;

    #[ORM\JoinTable(name: 'Item_tags')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    private $tags;

    #[ORM\Column(name: 'Rent', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private float $rent;

    #[ORM\Column(name: 'compensationPrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private float $compensationPrice;

    #[ORM\Column(name: 'RentNotice', type: 'string', length: 5000, nullable: true)]
    private string $rentNotice;

    #[ORM\Column(name: 'NeedsFixing', type: 'boolean', nullable: true)]
    private bool $needsFixing = false;

    #[ORM\Column(name: 'ToSpareParts', type: 'boolean')]
    private bool $toSpareParts = false;

    #[ORM\Column(name: 'CannotBeRented', type: 'boolean')]
    private bool $cannotBeRented = false;

    #[ORM\OneToMany(targetEntity: '\\' . \App\Entity\StatusEvent::class, mappedBy: 'item', cascade: ['all'], fetch: 'LAZY')]
    private $fixingHistory;

    #[ORM\OneToMany(targetEntity: '\\' . \App\Entity\File::class, mappedBy: 'product', cascade: ['all'])]
    private $files;

    #[ORM\ManyToMany(targetEntity: '\\' . \App\Entity\Booking::class, cascade: ['all'])]
    private $rentHistory;

    #[ORM\Column(name: 'History', type: 'string', length: 190, nullable: true)]
    private string $history;

    #[ORM\Column(name: 'ForSale', type: 'boolean', nullable: true)]
    private bool $forSale = false;

    #[ORM\ManyToMany(targetEntity: 'Package', inversedBy: 'items')]
    private $packages;

    #[ORM\Column(name: 'Commission', type: 'datetime', nullable: true)]
    private \DateTime $commission;

    #[ORM\Column(name: 'purchasePrice', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private $purchasePrice;

    #[ORM\ManyToOne(targetEntity: 'User')]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private $creator;

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(name: 'CreatedAt', type: 'datetime', nullable: true)]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: 'User')]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private $modifier;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(name: 'UpdatedAt', type: 'datetime', nullable: true)]
    private \DateTime $updatedAt;

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
     * @return Items
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
     * Set manufacturer
     *
     * @param string $manufacturer
     *
     * @return Item
     */
    public function setManufacturer($manufacturer)
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * Get manufacturer
     *
     * @return string
     */
    public function getManufacturer()
    {
        return $this->manufacturer;
    }

    /**
     * Set model
     *
     * @param string $model
     *
     * @return Item
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get model
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Item
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set rent
     *
     * @param float $rent
     *
     * @return Item
     */
    public function setRent($rent)
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
     *
     * @return Item
     */
    public function setRentNotice($rentNotice)
    {
        $this->rentNotice = $rentNotice;

        return $this;
    }

    /**
     * Get rentNotice
     *
     * @return string
     */
    public function getRentNotice()
    {
        return $this->rentNotice;
    }

    /**
     * Set needsFixing
     *
     * @param boolean $needsFixing
     *
     * @return Item
     */
    public function setNeedsFixing($needsFixing)
    {
        $this->needsFixing = $needsFixing;

        return $this;
    }

    /**
     * Get needsFixing
     *
     * @return boolean
     */
    public function getNeedsFixing()
    {
        return $this->needsFixing;
    }

    /**
     * Set history
     *
     * @param string $history
     *
     * @return Item
     */
    public function setHistory($history)
    {
        $this->history = $history;

        return $this;
    }

    /**
     * Get history
     *
     * @return string
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * Set forSale
     *
     * @param boolean $forSale
     *
     * @return Item
     */
    public function setForSale($forSale)
    {
        $this->forSale = $forSale;

        return $this;
    }

    /**
     * Get forSale
     *
     * @return boolean
     */
    public function getForSale()
    {
        return $this->forSale;
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
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
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

    /**
     * Add fixingHistory
     *
     *
     * @return Item
     */
    public function addFixingHistory(\App\Entity\StatusEvent $fixingHistory)
    {
        $fixingHistory->setProduct($this);
        $this->fixingHistory[] = $fixingHistory;

        return $this;
    }

    /**
     * Remove fixingHistory
     */
    public function removeFixingHistory(\App\Entity\StatusEvent $fixingHistory)
    {
        $fixingHistory->setProduct(null);
        $this->fixingHistory->removeElement($fixingHistory);
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Item
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Item
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get fixingHistory
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFixingHistory()
    {
        return $this->fixingHistory;
    }

    /**
     * Get fixingHistoryMessages
     *
     * @return string
     */
    public function getFixingHistoryMessages($count, $endofline = null)
    {
        if ($endofline == 'html') {
            $eol = "<br>";
        } else {
            $eol = PHP_EOL;
        }
        $messages = '';
        foreach (array_slice(array_reverse($this->getFixingHistory()->toArray()), 0, $count) as $event) {
            $user = $event->getCreator() ? $event->getCreator()->getUsername() : 'n/a';
            $messages .= '['.$event->getCreatedAt()->format('j.n.Y H:m').'] '.$user.': '.$event->getDescription().''.$eol;
        }
        if ($messages != null) {
            return $messages;
        } else {
            return 'no messages';
        }
    }

    /**
     * reset fixingHistory
     *
     * @return null
     */
    public function resetFixingHistory()
    {
        foreach ($this->getFixingHistory() as $fix) {
            $this->removeFixingHistory($fix);
        }
    }
    /**
     * reset whocanrent
     *
     * @return null
     */
    public function resetWhoCanRent()
    {
        foreach ($this->getWhoCanRent() as $who) {
            $this->removeWhoCanRent($who);
        }
    }
    /**
     * Set commission
     *
     * @param \DateTime $commission
     *
     * @return Item
     */
    public function setCommission($commission)
    {
        $this->commission = $commission;

        return $this;
    }

    /**
     * Get commission
     *
     * @return \DateTime
     */
    public function getCommission()
    {
        return $this->commission;
    }

    /**
     * Set serialnumber
     *
     * @param string $serialnumber
     *
     * @return Item
     */
    public function setSerialnumber($serialnumber)
    {
        $this->serialnumber = $serialnumber;

        return $this;
    }

    /**
     * Get serialnumber
     *
     * @return string
     */
    public function getSerialnumber()
    {
        return $this->serialnumber;
    }


    /**
     * Add file
     *
     *
     * @return Item
     */
    public function addFile(\App\Entity\File $file)
    {
        $file->setProduct($this);
        $this->files[] = $file;

        return $this;
    }

    /**
     * Remove file
     */
    public function removeFile(\App\Entity\File $file)
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
     *
     * @return Item
     */
    public function setPlaceinstorage($placeinstorage)
    {
        $this->placeinstorage = $placeinstorage;

        return $this;
    }

    /**
     * Get placeinstorage
     *
     * @return string
     */
    public function getPlaceinstorage()
    {
        return $this->placeinstorage;
    }

    /**
     * Add tag
     *
     *
     * @return Item
     */
    public function addTag(Tag $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove tag
     */
    public function removeTag(Tag $tag)
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
     *
     * @return Item
     */
    public function setCreator(\App\Entity\User $creator = null)
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
     *
     * @return Item
     */
    public function setModifier(\App\Entity\User $modifier = null)
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
     *
     * @return Item
     */
    public function addPackage(\App\Entity\Package $package)
    {
        $this->packages[] = $package;

        return $this;
    }

    /**
     * Remove package
     */
    public function removePackage(\App\Entity\Package $package)
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
     *
     * @return Item
     */
    public function setToSpareParts($toSpareParts)
    {
        $this->toSpareParts = $toSpareParts;

        return $this;
    }

    /**
     * Get toSpareParts
     *
     * @return boolean
     */
    public function getToSpareParts()
    {
        return $this->toSpareParts;
    }

    /**
     * Set packages
     *
     *
     * @return Item
     */
    public function setPackages(\App\Entity\Package $packages = null)
    {
        $this->packages = $packages;

        return $this;
    }

    /**
     * Add rentHistory
     *
     *
     * @return Item
     */
    public function addRentHistory(\App\Entity\Booking $rentHistory)
    {
        $this->rentHistory[] = $rentHistory;

        return $this;
    }

    /**
     * Remove rentHistory
     */
    public function removeRentHistory(\App\Entity\Booking $rentHistory)
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
    public function resetRentHistory()
    {
        foreach ($this->getRentHistory() as $rent) {
            $this->removeRentHistory($rent);
        }
    }

    /**
     * Set category
     *
     *
     * @return Item
     */
    public function setCategory(Category $category = null)
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
     *
     * @return Item
     */
    public function addWhoCanRent(\App\Entity\WhoCanRentChoice $whoCanRent)
    {
        $this->whoCanRent[] = $whoCanRent;

        return $this;
    }

    /**
     * Remove whoCanRent
     */
    public function removeWhoCanRent(\App\Entity\WhoCanRentChoice $whoCanRent)
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
     *
     * @return Item
     */
    public function setUrl($url = null)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string|null
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set cannotBeRented.
     *
     * @param bool $cannotBeRented
     *
     * @return Item
     */
    public function setCannotBeRented($cannotBeRented)
    {
        $this->cannotBeRented = $cannotBeRented;

        return $this;
    }

    /**
     * Get cannotBeRented.
     *
     * @return bool
     */
    public function getCannotBeRented()
    {
        return $this->cannotBeRented;
    }

    /**
     * Set compensationPrice.
     *
     * @param string|null $compensationPrice
     *
     * @return Item
     */
    public function setCompensationPrice($compensationPrice = null)
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
     *
     * @return Item
     */
    public function setPurchasePrice($purchasePrice = null)
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
}
