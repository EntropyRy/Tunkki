<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseGalleryItem;

#[ORM\Table(name: 'media__gallery_item')]
#[ORM\Entity]
class SonataMediaGalleryItem extends BaseGalleryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    public function getId(): int|string|null
    {
        return $this->id;
    }
}
