<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Accessory
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Accessory
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
     * @ORM\ManyToOne(targetEntity="Entropy\TunkkiBundle\Entity\AccessoryChoice", cascade={"persist"})
     */
    private $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="count", type="integer")
     */
    private $count;


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
     * Set count
     *
     * @param integer $count
     *
     * @return Accessory
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Get count
     *
     * @return integer
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set name
     *
     * @param \Entropy\TunkkiBundle\Entity\AccessoryChoice $name
     *
     * @return Accessory
     */
    public function setName(\Entropy\TunkkiBundle\Entity\AccessoryChoice $name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return \Entropy\TunkkiBundle\Entity\AccessoryChoice
     */
    public function getName()
    {
        return $this->name;
    }
}
