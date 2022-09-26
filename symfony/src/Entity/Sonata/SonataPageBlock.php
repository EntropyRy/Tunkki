<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BaseBlock;

/**
 * @ORM\Entity
 * @ORM\Table(name="page__block")
 */
class SonataPageBlock extends BaseBlock
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
