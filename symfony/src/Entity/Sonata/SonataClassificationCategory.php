<?php
namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseCategory;

/**
 * @ORM\Entity
 * @ORM\Table(name="classification__category")
 */
class SonataClassificationCategory extends BaseCategory
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
