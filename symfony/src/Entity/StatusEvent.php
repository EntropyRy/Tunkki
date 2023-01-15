<?php

namespace App\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Events
 */
#[ORM\Table(name: 'StatusEvent')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class StatusEvent implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Item::class, inversedBy: 'fixingHistory')]
    private ?\App\Entity\Item $item = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Booking::class, inversedBy: 'statusEvents')]
    private ?\App\Entity\Booking $booking = null;

    #[ORM\Column(name: 'Description', type: 'string', length: 5000, nullable: true)]
    private string $description;

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(name: 'CreatedAt', type: 'datetime')]
    private \DateTime $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(name: 'UpdatedAt', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\ManyToOne(targetEntity: '\\' . \App\Entity\User::class)]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?\App\Entity\User $creator = null;

    #[ORM\ManyToOne(targetEntity: '\\' . \App\Entity\User::class)]
    #[ORM\JoinColumn(name: 'modifier_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?\App\Entity\User $modifier = null;

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
     * @return Events
     */
    public function setItem(\App\Entity\Item $item = null)
    {
        $this->item = $item;

        return $this;
    }

    /**
     * Get item
     *
     * @return \App\Entity\Item
     */
    public function getItem()
    {
        return $this->item;
    }

    public function __toString(): string
    {
        if (is_object($this->getItem())) {
            return 'Event for '.$this->getItem()->getName();
        } elseif (is_object($this->getBooking())) {
            return 'Event for '.$this->getBooking()->getName();
        } else {
            return 'No associated item';
        }
    }

    /**
     * Set creator
     *
     * @return Event
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
     * @return Event
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
     * Set booking.
     *
     * @param \App\Entity\Booking|null $booking
     *
     * @return Event
     */
    public function setBooking(\App\Entity\Booking $booking = null)
    {
        $this->booking = $booking;

        return $this;
    }

    /**
     * Get booking.
     *
     * @return \App\Entity\Booking|null
     */
    public function getBooking()
    {
        return $this->booking;
    }
}
