<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AccessoryChoices
 */
#[ORM\Table('AccessoryChoice')]
#[ORM\Entity]
class AccessoryChoice implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private readonly int $id;

    #[ORM\Column(name: 'name', type: 'string', length: 190)]
    private string $name;

    #[ORM\Column(name: 'compensationPrice', type: 'integer')]
    private string $compensationPrice;

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

    public function __toString(): string
    {
        return $this->name ?: '';
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
