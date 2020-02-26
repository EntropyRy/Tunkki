<?php

namespace App\Entity;

use App\Application\Sonata\PageBundle\Entity\Page;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @Gedmo\Tree(type="nested")
 * @ORM\Entity(repositoryClass="App\Repository\MenuRepository")
 */
class Menu
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=180)
     */
    private $nimi;

    /**
     * @ORM\Column(type="string", length=180, nullable=true)
     */
    private $url;

    /**
     * @ORM\ManyToOne(targetEntity="App\Application\Sonata\PageBundle\Entity\Page")
     */
    private $pageFi;

    /**
     * @ORM\ManyToOne(targetEntity="App\Application\Sonata\PageBundle\Entity\Page")
     */
    private $pageEn;

    /**
     * @ORM\Column(type="boolean")
     */
    private $enabled;

        /**
     * @Gedmo\TreeLeft
     * @ORM\Column(type="integer")
     */
    private $lft;

    /**
     * @Gedmo\TreeLevel
     * @ORM\Column(type="integer")
     */
    private $lvl;

    /**
     * @Gedmo\TreeRight
     * @ORM\Column(type="integer")
     */
    private $rgt;
    /**
     * @Gedmo\TreeRoot
     * @ORM\ManyToOne(targetEntity="Menu")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $root;

    /**
     * @ORM\Column(type="integer")
     */
    private $position;
    /**
     * @Gedmo\TreeParent
     * @ORM\ManyToOne(targetEntity="Menu", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $parent;
    /**
     * @ORM\OneToMany(targetEntity="Menu", mappedBy="parent")
     */
    private $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getNimi(): ?string
    {
        return $this->nimi;
    }

    public function setNimi(string $nimi): self
    {
        $this->nimi = $nimi;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLft(): ?int
    {
        return $this->lft;
    }

    public function setLft(int $lft): self
    {
        $this->lft = $lft;

        return $this;
    }

    public function getLvl(): ?int
    {
        return $this->lvl;
    }

    public function setLvl(int $lvl): self
    {
        $this->lvl = $lvl;

        return $this;
    }

    public function getRgt(): ?int
    {
        return $this->rgt;
    }

    public function setRgt(int $rgt): self
    {
        $this->rgt = $rgt;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getRoot(): ?self
    {
        return $this->root;
    }

    public function setRoot(?self $root): self
    {
        $this->root = $root;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection|Menu[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Menu $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Menu $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }
    public function __toString()
    {
        return $this->label ? $this->label : 'n/a';
    }

    public function getPageFi(): ?Page
    {
        return $this->pageFi;
    }

    public function setPageFi(?Page $pageFi): self
    {
        $this->pageFi = $pageFi;

        return $this;
    }

    public function getPageEn(): ?Page
    {
        return $this->pageEn;
    }

    public function setPageEn(?Page $pageEn): self
    {
        $this->pageEn = $pageEn;

        return $this;
    }
}
