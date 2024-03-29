<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WhoCanRentCoice
 */
#[ORM\Table(name: 'WhoCanRentChoice')]
#[ORM\Entity]
class WhoCanRentChoice implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 190)]
    private string $name;

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
     * @return WhoCanRentChoice
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
}
