<?php
namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseTag;

/**
 * @ORM\Entity
 * @ORM\Table(name="classification__tag")
 */
class SonataClassificationTag extends BaseTag
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
