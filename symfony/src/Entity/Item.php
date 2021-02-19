<?php

namespace App\Entity;

use App\Application\Sonata\ClassificationBundle\Entity\Category;
use App\Application\Sonata\ClassificationBundle\Entity\Tag;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Item
 *
 * @ORM\Table(name="Item")
 * @ORM\Entity(repositoryClass="App\Repository\ItemsRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Item
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
     * @ORM\Column(name="Name", type="string", length=190)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="Manufacturer", type="string", length=190, nullable=true)
     */
    private $manufacturer;

    /**
     * @var string
     *
     * @ORM\Column(name="Model", type="string", length=190, nullable=true)
     */
    private $model;

    /**
     * @var string
     *
     * @ORM\Column(name="Url", type="string", length=500, nullable=true)
     */
    private $url;

    /**
     * @var string
     *
     * @ORM\Column(name="SerialNumber", type="string", length=190, nullable=true)
     */
    private $serialnumber;

    /**
     * @var string
     *
     * @ORM\Column(name="PlaceInStorage", type="string", length=190, nullable=true)
     */
    private $placeinstorage;

    /**
     * @var string
     *
     * @ORM\Column(name="Description", type="string", length=4000, nullable=true)
     */
    private $description;

    /**
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\WhoCanRentChoice", cascade={"persist"})
     */
    private $whoCanRent;

    /**
     * @ORM\ManyToOne(targetEntity="App\Application\Sonata\ClassificationBundle\Entity\Category", cascade={"persist"})
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id")
     */
    private $category;

    /**
     * @ORM\ManyToMany(targetEntity="App\Application\Sonata\ClassificationBundle\Entity\Tag", cascade={"persist"})
     * @ORM\JoinTable(
     *      name="Item_tags",
     *      joinColumns={@ORM\JoinColumn(name="item_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="tag_id", referencedColumnName="id")}
     * )
     */
    private $tags;

    /**
     * @var float
     *
     * @ORM\Column(name="Rent", type="decimal", precision=7, scale=2, nullable=true)
     */
    private $rent;

    /**
     * @var float
     *
     * @ORM\Column(name="compensationPrice", type="decimal", precision=7, scale=2, nullable=true)
     */
    private $compensationPrice;

    /**
     * @var string
     *
     * @ORM\Column(name="RentNotice", type="string", length=5000, nullable=true)
     */
    private $rentNotice;

    /**
     * @var boolean
     *
     * @ORM\Column(name="NeedsFixing", type="boolean", nullable=true)
     */
    private $needsFixing = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="ToSpareParts", type="boolean")
     */
    private $toSpareParts = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="CannotBeRented", type="boolean")
     */
    private $cannotBeRented = false;

    /**
     * @ORM\OneToMany(targetEntity="\App\Entity\StatusEvent", mappedBy="item", cascade={"all"}, fetch="LAZY")
     */
    private $fixingHistory;

    /**
     * @ORM\OneToMany(targetEntity="\App\Entity\File", mappedBy="product", cascade={"all"})
     */
    private $files;

    /**
     * @ORM\ManyToMany(targetEntity="\App\Entity\Booking", cascade={"all"})
     */
    private $rentHistory;

    /**
     * @var string
     *
     * @ORM\Column(name="History", type="string", length=190, nullable=true)
     */
    private $history;

    /**
     * @var boolean
     *
     * @ORM\Column(name="ForSale", type="boolean", nullable=true)
     */
    private $forSale = false;

    /**
     * @ORM\ManyToMany(targetEntity="Package", inversedBy="items")
     */
    private $packages;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="Commission", type="datetime", nullable=true)
     */
    private $commission;

    /**
     * @ORM\Column(name="purchasePrice", type="decimal", precision=7, scale=2, nullable=true)
     */
    private $purchasePrice;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="creator_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $creator;

    /**
     * @var \DateTime
     * 
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="CreatedAt", type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="modifier_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $modifier;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="UpdatedAt", type="datetime", nullable=true)
     */
    private $updatedAt;

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
     * @return Items
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
     * @return Items
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
     * @return Items
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
     * @return Items
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
     * @return Items
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
     * @return Items
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
     * @return Items
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
     * @return Items
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

    public function __toString()
    {
        if ($this->name){
            return $this->name;
        }
        else{ return 'N/A';}
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->fixingHistory = new \Doctrine\Common\Collections\ArrayCollection();
        $this->tags = new \Doctrine\Common\Collections\ArrayCollection();
        $this->toSpareParts = false;
        $this->whoCanRent = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->rentHistory = new ArrayCollection();
        $this->packages = new ArrayCollection();
    }

    /**
     * Add fixingHistory
     *
     * @param \App\Entity\StatusEvent $fixingHistory
     *
     * @return Items
     */
    public function addFixingHistory(\App\Entity\StatusEvent $fixingHistory)
    {
        $fixingHistory->setProduct($this);
        $this->fixingHistory[] = $fixingHistory;

        return $this;
    }

    /**
     * Remove fixingHistory
     *
     * @param \App\Entity\StatusEvent $fixingHistory
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
     * @return Items
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
     * @return Items
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
        if($endofline == 'html'){
            $eol = "<br>";
        } else {
            $eol = PHP_EOL;
        }
        $messages = '';
        foreach (array_slice(array_reverse($this->getFixingHistory()->toArray()),0,$count) as $event){
            $user = $event->getCreator() ? $event->getCreator()->getUsername() : 'n/a';
            $messages .= '['.$event->getCreatedAt()->format('j.n.Y H:m').'] '.$user.': '.$event->getDescription().''.$eol;
        }
        if($messages != NULL){
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
        foreach ($this->getFixingHistory() as $fix){
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
        foreach ($this->getWhoCanRent() as $who){
            $this->removeWhoCanRent($who);
        }
    }
    /**
     * Set commission
     *
     * @param \DateTime $commission
     *
     * @return Items
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
     * @return Items
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
     * @param \App\Entity\File $file
     *
     * @return Items
     */
    public function addFile(\App\Entity\File $file)
    {
        $file->setProduct($this);
        $this->files[] = $file;

        return $this;
    }

    /**
     * Remove file
     *
     * @param \App\Entity\File $file
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
     * @return Items
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
     * @param \App\Application\Sonata\ClassificationBundle\Entity\Tag $tag
     *
     * @return Items
     */
    public function addTag(\App\Application\Sonata\ClassificationBundle\Entity\Tag $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove tag
     *
     * @param \App\Application\Sonata\ClassificationBundle\Entity\Tag $tag
     */
    public function removeTag(\App\Application\Sonata\ClassificationBundle\Entity\Tag $tag)
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
     * @param \App\Entity\User $creator
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
     * @param \App\Entity\User $modifier
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
     * @param \App\Entity\Package $package
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
     *
     * @param \App\Entity\Package $package
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
     * @param \App\Entity\Package $packages
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
     * @param \App\Entity\Booking $rentHistory
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
     *
     * @param \App\Entity\Booking $rentHistory
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
        foreach ($this->getRentHistory() as $rent){
            $this->removeRentHistory($rent);
        }
    }

    /**
     * Set category
     *
     * @param \App\Application\Sonata\ClassificationBundle\Entity\Category $category
     *
     * @return Item
     */
    public function setCategory(\App\Application\Sonata\ClassificationBundle\Entity\Category $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return \App\Application\Sonata\ClassificationBundle\Entity\Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Add whoCanRent
     *
     * @param \App\Entity\WhoCanRentChoice $whoCanRent
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
     *
     * @param \App\Entity\WhoCanRentChoice $whoCanRent
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
     * Set commissionPrice
     *
     * @param string $commissionPrice
     *
     * @return Item
     */
    public function setCommissionPrice($commissionPrice)
    {
        $this->commissionPrice = $commissionPrice;

        return $this;
    }

    /**
     * Get commissionPrice
     *
     * @return string
     */
    public function getCommissionPrice()
    {
        return $this->commissionPrice;
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
