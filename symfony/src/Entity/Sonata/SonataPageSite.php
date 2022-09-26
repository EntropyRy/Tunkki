<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BaseSite;

/**
 * @ORM\Entity
 * @ORM\Table(name="page__site")
 */
class SonataPageSite extends BaseSite
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
