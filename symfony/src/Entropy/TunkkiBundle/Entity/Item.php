<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Items
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Entropy\TunkkiBundle\Entity\ItemsRepository")
 * @ORM\HasLifecycleCallbacks 
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
     * @ORM\Column(type="integer")
     * @ORM\Version
     */
    protected $version;

    /**
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="Manufacturer", type="string", length=255, nullable=true)
     */
    private $manufacturer;

    /**
     * @var string
     *
     * @ORM\Column(name="Model", type="string", length=255, nullable=true)
     */
    private $model;

    /**
     * @var string
     *
     * @ORM\Column(name="SerialNumber", type="string", length=255, nullable=true)
     */
    private $serialnumber;

    /**
     * @var string
     *
     * @ORM\Column(name="PlaceInStorage", type="string", length=255, nullable=true)
     */
    private $placeinstorage;

    /**
     * @var string
     *
     * @ORM\Column(name="Description", type="string", length=4000, nullable=true)
     */
    private $description;

    /**
     * @var simple_array
     *
     * @ORM\Column(name="WhoCanRent", type="simple_array", length=255, nullable=true)
     */
    private $whoCanRent;

    /**
     * @ORM\ManyToMany(targetEntity="Application\Sonata\ClassificationBundle\Entity\Tag", cascade={"persist"})
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
     * @ORM\Column(name="Rent", type="float", nullable=true)
     */
    private $rent;

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
    private $needsFixing;

    /**
     * @var boolean
     *
     * @ORM\Column(name="ToSpareParts", type="boolean", nullable=true)
     */
    private $toSpareParts;

    /**
     * @ORM\OneToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Event", mappedBy="product", cascade={"all"}, fetch="EAGER")
     */
    private $fixingHistory;

    /**
     * @ORM\OneToMany(targetEntity="\Entropy\TunkkiBundle\Entity\File", mappedBy="product", cascade={"all"})
     */
    private $files;

    /**
     * @var string
     *
     * @ORM\Column(name="RentHistory", type="string", length=255, nullable=true)
     */
    private $rentHistory;

    /**
     * @var string
     *
     * @ORM\Column(name="History", type="string", length=255, nullable=true)
     */
    private $history;

    /**
     * @var boolean
     *
     * @ORM\Column(name="ForSale", type="boolean", nullable=true)
     */
    private $forSale;

    /**
     * @var \DateTime
     * 
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="CreatedAt", type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="UpdatedAt", type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="creator_id", referencedColumnName="id")
     */
    private $creator;

    /**
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="modifier_id", referencedColumnName="id")
     */
    private $modifier;

    /**
     * @ORM\ManyToOne(targetEntity="Pakage", inversedBy="items")
     */
    private $pakages;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="Commission", type="datetime", nullable=true)
     */
    private $commission;

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
     * Set rentHistory
     *
     * @param string $rentHistory
     *
     * @return Items
     */
    public function setRentHistory($rentHistory)
    {
        $this->rentHistory = $rentHistory;

        return $this;
    }

    /**
     * Get rentHistory
     *
     * @return string
     */
    public function getRentHistory()
    {
        return $this->rentHistory;
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
        if ($this->getName()){
            return $this->getName();
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
    }

    /**
     * Add fixingHistory
     *
     * @param \Entropy\TunkkiBundle\Entity\Event $fixingHistory
     *
     * @return Items
     */
    public function addFixingHistory(\Entropy\TunkkiBundle\Entity\Event $fixingHistory)
    {
        $fixingHistory->setProduct($this);
        $this->fixingHistory[] = $fixingHistory;

        return $this;
    }

    /**
     * Remove fixingHistory
     *
     * @param \Entropy\TunkkiBundle\Entity\Event $fixingHistory
     */
    public function removeFixingHistory(\Entropy\TunkkiBundle\Entity\Event $fixingHistory)
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
     * @param \Entropy\TunkkiBundle\Entity\File $file
     *
     * @return Items
     */
    public function addFile(\Entropy\TunkkiBundle\Entity\File $file)
    {
        $file->setProduct($this);
        $this->files[] = $file;

        return $this;
    }

    /**
     * Remove file
     *
     * @param \Entropy\TunkkiBundle\Entity\File $file
     */
    public function removeFile(\Entropy\TunkkiBundle\Entity\File $file)
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
     * @param \Application\Sonata\ClassificationBundle\Entity\Tag $tag
     *
     * @return Items
     */
    public function addTag(\Application\Sonata\ClassificationBundle\Entity\Tag $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove tag
     *
     * @param \Application\Sonata\ClassificationBundle\Entity\Tag $tag
     */
    public function removeTag(\Application\Sonata\ClassificationBundle\Entity\Tag $tag)
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
     * Set version
     *
     * @param integer $version
     *
     * @return Item
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return integer
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set whoCanRent
     *
     * @param array $whoCanRent
     *
     * @return Item
     */
    public function setWhoCanRent($whoCanRent)
    {
        $this->whoCanRent = $whoCanRent;

        return $this;
    }

    /**
     * Get whoCanRent
     *
     * @return array
     */
    public function getWhoCanRent()
    {
        return $this->whoCanRent;
    }

    /**
     * Set creator
     *
     * @param \Application\Sonata\UserBundle\Entity\User $creator
     *
     * @return Item
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
     * @return Item
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
     * Add pakage
     *
     * @param \Entropy\TunkkiBundle\Entity\Pakage $pakage
     *
     * @return Item
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
     * Set pakages
     *
     * @param \Entropy\TunkkiBundle\Entity\Pakage $pakages
     *
     * @return Item
     */
    public function setPakages(\Entropy\TunkkiBundle\Entity\Pakage $pakages = null)
    {
        $this->pakages = $pakages;

        return $this;
    }
}
