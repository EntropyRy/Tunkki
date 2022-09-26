<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BaseSnapshot;

/**
 * @ORM\Entity
 * @ORM\Table(name="page__snapshot")
 */
class SonataPageSnapshot extends BaseSnapshot
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
