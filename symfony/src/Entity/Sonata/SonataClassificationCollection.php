<?php
namespace App\Entity\Sonata;
use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseCollection;

/**
 * @ORM\Entity
 * @ORM\Table(name="classification__collection")
 */
class SonataClassificationCollection extends BaseCollection
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
