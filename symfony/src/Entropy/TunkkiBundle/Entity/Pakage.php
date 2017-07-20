<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Pakage
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Entropy\TunkkiBundle\Entity\PakageRepository")
 */
class Pakage
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
     * @ORM\ManyToMany(targetEntity="\Entropy\TunkkiBundle\Entity\Item", mappedBy="pakages", cascade={"all"})
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
    private $needsFixing;

    /**
     * @ORM\ManyToMany(targetEntity="Application\Sonata\ClassificationBundle\Entity\Tag", cascade={"persist"})
     * @ORM\JoinTable(
     *      name="Pakage_tags",
     *      joinColumns={@ORM\JoinColumn(name="item_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="tag_id", referencedColumnName="id")}
     * )
     */
    private $tags;

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
     * @return Pakage
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
     * @return Pakage
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
     * @return Pakage
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
        $this->tags = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add item
     *
     * @param \Entropy\TunkkiBundle\Entity\Item $item
     *
     * @return Pakage
     */
    public function addItem(\Entropy\TunkkiBundle\Entity\Item $item)
    {
        $item->addPakage($this);
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
        $item->pakages->removeElement($this);
        $this->items->removeElement($item);
    }

    /**
     * Add tag
     *
     * @param \Application\Sonata\ClassificationBundle\Entity\Tag $tag
     *
     * @return Pakage
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
     * Set name
     *
     * @param string $name
     *
     * @return Pakage
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
        return $this->name ? $this->name: 'n/a';
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
}
