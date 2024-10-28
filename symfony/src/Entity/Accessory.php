<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AccessoryChoice::class, cascade: ['persist'])]
    private ?AccessoryChoice $name = null;

    #[ORM\Column(name: 'count', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    private ?string $count = null;


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
     * @return Accessory
     */
    public function setName(AccessoryChoice $name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return AccessoryChoice
     */
    public function getName()
    {
        return $this->name;
    }

    #[\Override]
    public function __toString(): string
    {
        $name = $this->name ?: 'n/a';
        $count = $this->count ?: '';
        return $count .' X '.$name;
    }
}
