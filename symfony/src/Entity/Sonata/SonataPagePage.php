<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BasePage;

/**
 * @ORM\Entity
 * @ORM\Table(name="page__page")
 */
class SonataPagePage extends BasePage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
