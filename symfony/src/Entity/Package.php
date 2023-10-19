<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'Package')]
#[ORM\Entity(repositoryClass: \App\Repository\PackagesRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Package implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: Item::class, mappedBy: 'packages', orphanRemoval: false, fetch: 'EAGER')]
    private ?Collection $items;

    #[ORM\Column(name: 'name', type: 'string', length: 190)]
    private string $name;

    #[ORM\Column(name: 'rent', type: 'string', length: 190)]
    private string $rent;

    #[ORM\Column(name: 'needs_fixing', type: 'boolean')]
    private bool $needsFixing = false;

    #[ORM\ManyToMany(targetEntity: WhoCanRentChoice::class, cascade: ['persist'])]
    private ?Collection $whoCanRent;

    #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $compensationPrice = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setRent($rent): self
    {
        $this->rent = $rent;

        return $this;
    }

    public function getRent(): string
    {
        return $this->rent;
    }

    public function setNeedsFixing($needsFixing): self
    {
        $this->needsFixing = $needsFixing;

        return $this;
    }

    public function getNeedsFixing(): bool
    {
        return $this->needsFixing;
    }

    public function setNotes($notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->whoCanRent = new ArrayCollection();
    }

    public function addItem(Item $item): self
    {
        $item->addPackage($this);
        $this->items[] = $item;

        return $this;
    }

    public function removeItem(\App\Entity\Item $item): void
    {
        $item->getPackages()->removeElement($this);
        $this->items->removeElement($item);
    }

    public function setName($name): Package
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
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

    public function getItems()
    {
        return $this->items;
    }

    public function getRentFromItems(): int
    {
        $price = 0;
        foreach ($this->getItems() as $item) {
            $price += $item->getRent();
        }
        return $price;
    }
    public function getItemsNeedingFixing(): ArrayCollection
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

    public function getIsSomethingBroken(): bool
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

    public function addWhoCanRent(WhoCanRentChoice $whoCanRent): self
    {
        $this->whoCanRent[] = $whoCanRent;

        return $this;
    }

    public function removeWhoCanRent(WhoCanRentChoice $whoCanRent): void
    {
        $this->whoCanRent->removeElement($whoCanRent);
    }

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
