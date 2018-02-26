<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Package
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Entropy\TunkkiBundle\Entity\PackagesRepository")
 */
class Package
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
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Item", mappedBy="packages", cascade={"all"}, fetch="EAGER")
     */
    private $items;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="rent", type="string", length=255)
     */
    private $rent;

    /**
     * @var boolean
     *
     * @ORM\Column(name="needs_fixing", type="boolean")
     */
    private $needsFixing = false;

    /**
     *
     * @ORM\ManyToMany(targetEntity="Entropy\TunkkiBundle\Entity\WhoCanRentChoice", cascade={"persist"})
     */
    private $whoCanRent;

    /**
     * @var string
     *
     * @ORM\Column(name="notes", type="text", nullable=true)
     */
    private $notes;


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
     * Set rent
     *
     * @param string $rent
     *
     * @return Package
     */
    public function setRent($rent)
    {
        $this->rent = $rent;

        return $this;
    }

    /**
     * Get rent
     *
     * @return string
     */
    public function getRent()
    {
        return $this->rent;
    }

    /**
     * Set needsFixing
     *
     * @param boolean $needsFixing
     *
     * @return Package
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
     * Set notes
     *
     * @param string $notes
     *
     * @return Package
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->items = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add item
     *
     * @param \Entropy\TunkkiBundle\Entity\Item $item
     *
     * @return Package
     */
    public function addItem(\Entropy\TunkkiBundle\Entity\Item $item)
    {
        $item->addPackage($this);
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
        $item->packages->removeElement($this);
        $this->items->removeElement($item);
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Package
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
    
    public function __toString()
    {
        $return = $this->name ? $this->name: 'n/a';
    /*    if ($return != 'n/a'){
            $return .= ' = ';
            foreach ($this->getItems() as $item){
                $return .= $item->getName().', ';
            }
        }*/
        return $return;
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

    public function getRentFromItems()
    {
        $price = 0;
        foreach ($this->getItems() as $item) {
            $price += $item->getRent();
        }
        return $price;
    }
    public function getItemsNeedingFixing()
    {
        $needsfix = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($this->getItems() as $item) {
            if ($item->getneedsFixing())
                $needsfix[] = $item;
                $this->setNeedsFixing(true);
        }
        return $needsfix;
    }

    /**
     * Get somethingBroken
     *
     * @return boolean
     */
    public function getIsSomethingBroken()
    {
        if($this->getItems()){
            foreach ($this->getItems() as $item) {
                if($item->getNeedsFixing() == true){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Add whoCanRent
     *
     * @param \Entropy\TunkkiBundle\Entity\WhoCanRentChoice $whoCanRent
     *
     * @return Package
     */
    public function addWhoCanRent(\Entropy\TunkkiBundle\Entity\WhoCanRentChoice $whoCanRent)
    {
        $this->whoCanRent[] = $whoCanRent;

        return $this;
    }

    /**
     * Remove whoCanRent
     *
     * @param \Entropy\TunkkiBundle\Entity\WhoCanRentChoice $whoCanRent
     */
    public function removeWhoCanRent(\Entropy\TunkkiBundle\Entity\WhoCanRentChoice $whoCanRent)
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
}
