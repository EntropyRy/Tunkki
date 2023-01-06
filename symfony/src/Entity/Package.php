<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Package
 */
#[ORM\Table(name: 'Package')]
#[ORM\Entity(repositoryClass: \App\Repository\PackagesRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Package implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private readonly int $id;

    #[ORM\ManyToMany(targetEntity: '\\' . \App\Entity\Item::class, mappedBy: 'packages', orphanRemoval: false, fetch: 'EAGER')]
    private \Doctrine\Common\Collections\ArrayCollection|array $items;

    #[ORM\Column(name: 'name', type: 'string', length: 190)]
    private string $name;

    #[ORM\Column(name: 'rent', type: 'string', length: 190)]
    private string $rent;

    #[ORM\Column(name: 'needs_fixing', type: 'boolean')]
    private bool $needsFixing = false;

    #[ORM\ManyToMany(targetEntity: \App\Entity\WhoCanRentChoice::class, cascade: ['persist'])]
    private \Doctrine\Common\Collections\ArrayCollection|array $whoCanRent;

    #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $compensationPrice = null;


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
        $this->whoCanRent = new ArrayCollection();
    }

    /**
     * Add item
     *
     *
     * @return Package
     */
    public function addItem(\App\Entity\Item $item)
    {
        $item->addPackage($this);
        $this->items[] = $item;

        return $this;
    }

    /**
     * Remove item
     */
    public function removeItem(\App\Entity\Item $item)
    {
        $item->getPackages()->removeElement($this);
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

    public function __toString(): string
    {
        $return = $this->name ?: 'n/a';
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
            if ($item->getneedsFixing()) {
                $needsfix[] = $item;
                $this->setNeedsFixing(true);
            }
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
        if ($this->getItems()) {
            foreach ($this->getItems() as $item) {
                if ($item->getNeedsFixing() == true) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Add whoCanRent
     *
     *
     * @return Package
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

    public function getCompensationPrice(): ?string
    {
        return $this->compensationPrice;
    }

    public function setCompensationPrice(?string $compensationPrice): self
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }
}
