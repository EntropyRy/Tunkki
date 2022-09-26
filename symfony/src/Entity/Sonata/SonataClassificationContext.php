<?php
namespace App\Entity\Sonata;
use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseContext;

/**
 * @ORM\Entity
 * @ORM\Table(name="classification__context")
 */
class SonataClassificationContext extends BaseContext
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
