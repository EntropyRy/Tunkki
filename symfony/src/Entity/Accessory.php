<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Accessory
 */
#[ORM\Table(name: 'Accessory')]
#[ORM\Entity]
class Accessory implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private readonly int $id;

    #[ORM\ManyToOne(targetEntity: \App\Entity\AccessoryChoice::class, cascade: ['persist'])]
    private $name;

    #[ORM\Column(name: 'count', type: 'string', length: 50)]
    private string $count;


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
     * @param string $count
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
     * @return string
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set name
     *
     *
     * @return Accessory
     */
    public function setName(\App\Entity\AccessoryChoice $name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return \App\Entity\AccessoryChoice
     */
    public function getName()
    {
        return $this->name;
    }

    public function __toString(): string
    {
        $name = $this->name ?: 'n/a';
        $count = $this->count ?: '';
        return $count .' X '.$name;
    }
}
