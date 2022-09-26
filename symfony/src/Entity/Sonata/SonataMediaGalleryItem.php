<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseGalleryItem;

/**
 * @ORM\Entity
 * @ORM\Table(name="media__gallery_item")
 */
class SonataMediaGalleryItem extends BaseGalleryItem
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
