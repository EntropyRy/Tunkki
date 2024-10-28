<?php

namespace App\Entity;

use App\Entity\Sonata\SonataPagePage as Page;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

#[Gedmo\Tree(type: 'nested')]
#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
class Menu implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 180)]
    private ?string $label = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 180)]
    private ?string $nimi = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 180, nullable: true)]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    private ?\App\Entity\Sonata\SonataPagePage $pageFi = null;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    private ?\App\Entity\Sonata\SonataPagePage $pageEn = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $enabled = null;

    #[Gedmo\TreeLeft]
    #[ORM\Column(type: 'integer')]
    private ?int $lft = null;

    #[Gedmo\TreeLevel]
    #[ORM\Column(type: 'integer')]
    private ?int $lvl = null;

    #[Gedmo\TreeRight]
    #[ORM\Column(type: 'integer')]
    private ?int $rgt = null;

    #[Gedmo\TreeRoot]
    #[ORM\ManyToOne(targetEntity: Menu::class)]
    #[ORM\JoinColumn(referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?\App\Entity\Menu $root = null;

    #[ORM\Column(type: 'integer')]
    private ?int $position = null;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?\App\Entity\Menu $parent = null;

    #[ORM\OneToMany(targetEntity: Menu::class, mappedBy: 'parent')]
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

    public function hasChildren(): bool
    {
        if (count($this->children) > 0) {
            return true;
        }

        return false;
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
    #[\Override]
    public function __toString(): string
    {
        return $this->label ?: 'n/a';
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
    public function getPageByLang(string $lang)
    {
        $func = 'page' . ucfirst($lang);
        return $this->{$func};
    }
}
