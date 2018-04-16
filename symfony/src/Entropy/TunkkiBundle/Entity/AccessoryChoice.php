<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AccessoryChoices
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class AccessoryChoice
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
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="compensationPrice", type="integer")
     */
    private $compensationPrice;

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
     * Set name
     *
     * @param string $name
     *
     * @return AccessoryChoices
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

    /**
     * Set price
     *
     * @param integer $price
     *
     * @return AccessoryChoices
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price
     *
     * @return integer
     */
    public function getPrice()
    {
        return $this->price;
    }

    public function __toString()
    {
        return $this->name ? $this->name : '';
    }

    /**
     * Set compensationPrice.
     *
     * @param int $compensationPrice
     *
     * @return AccessoryChoice
     */
    public function setCompensationPrice($compensationPrice)
    {
        $this->compensationPrice = $compensationPrice;

        return $this;
    }

    /**
     * Get compensationPrice.
     *
     * @return int
     */
    public function getCompensationPrice()
    {
        return $this->compensationPrice;
    }
}
