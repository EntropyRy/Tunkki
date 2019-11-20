<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Events
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Entropy\TunkkiBundle\Repository\EventsRepository")
 * @ORM\HasLifecycleCallbacks 
 */
class Event
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
     * 
     * @ORM\ManyToOne(targetEntity="Entropy\TunkkiBundle\Entity\Item", inversedBy="fixingHistory")
     */
    private $item;

    /**
     * 
     * @ORM\ManyToOne(targetEntity="Entropy\TunkkiBundle\Entity\Booking", inversedBy="statusEvents")
     */
    private $booking;

    /**
     * @var string
     *
     * @ORM\Column(name="Description", type="string", length=5000, nullable=true)
     */
    private $description;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="CreatedAt", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="UpdatedAt", type="datetime")
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="creator_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $creator;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="\Application\Sonata\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="modifier_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $modifier;

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
     * Set description
     *
     * @param string $description
     *
     * @return Events
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
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Events
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
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
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Events
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
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

    /**
     * Set item
     *
     * @param \Entropy\TunkkiBundle\Entity\Item $item
     *
     * @return Events
     */
    public function setItem(\Entropy\TunkkiBundle\Entity\Item $item = null)
    {
        $this->item = $item;

        return $this;
    }

    /**
     * Get item
     *
     * @return \Entropy\TunkkiBundle\Entity\Item
     */
    public function getItem()
    {
        return $this->item;
    }

    public function __toString()
    {
        if(is_object($this->getItem())){
            return 'Event for '.$this->getItem()->getName();
        } elseif(is_object($this->getBooking())){
            return 'Event for '.$this->getBooking()->getName();
        } else {
            return 'No associated item';
        }
    }

    /**
     * Set creator
     *
     * @param \Application\Sonata\UserBundle\Entity\User $creator
     *
     * @return Event
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
     * @return Event
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
     * Set booking.
     *
     * @param \Entropy\TunkkiBundle\Entity\Booking|null $booking
     *
     * @return Event
     */
    public function setBooking(\Entropy\TunkkiBundle\Entity\Booking $booking = null)
    {
        $this->booking = $booking;

        return $this;
    }

    /**
     * Get booking.
     *
     * @return \Entropy\TunkkiBundle\Entity\Booking|null
     */
    public function getBooking()
    {
        return $this->booking;
    }
}
