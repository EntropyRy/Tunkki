<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PackagesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'Package')]
#[ORM\Entity(repositoryClass: PackagesRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Package implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\ManyToMany(targetEntity: Item::class, mappedBy: 'packages', fetch: 'EAGER', orphanRemoval: false)]
    private Collection $items;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 190)]
    private string $name = '';

    #[ORM\Column(name: 'rent', type: Types::STRING, length: 190)]
    private string $rent = '';

    #[ORM\Column(name: 'needs_fixing', type: Types::BOOLEAN)]
    private bool $needsFixing = false;

    /**
     * @var Collection<int, WhoCanRentChoice>
     */
    #[ORM\ManyToMany(targetEntity: WhoCanRentChoice::class, cascade: ['persist'])]
    private Collection $whoCanRent;

    #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $compensationPrice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setRent(string $rent): self
    {
        $this->rent = $rent;

        return $this;
    }

    public function getRent(): string
    {
        return $this->rent;
    }

    public function setNeedsFixing(bool $needsFixing): self
    {
        $this->needsFixing = $needsFixing;

        return $this;
    }

    public function getNeedsFixing(): bool
    {
        return $this->needsFixing;
    }

    public function setNotes(?string $notes): self
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

    public function removeItem(Item $item): void
    {
        $item->getPackages()->removeElement($this);
        $this->items->removeElement($item);
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

    #[\Override]
    public function __toString(): string
    {
        /*    if ($return != 'n/a'){
                $return .= ' = ';
                foreach ($this->getItems() as $item){
                    $return .= $item->getName().', ';
                }
            }*/
        return $this->name ?: 'n/a';
    }

    public function getItems(): Collection
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
        $needsfix = new ArrayCollection();
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
        foreach ($this->getItems() as $item) {
            if (true == $item->getNeedsFixing()) {
                return true;
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

    public function getWhoCanRent(): Collection
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
