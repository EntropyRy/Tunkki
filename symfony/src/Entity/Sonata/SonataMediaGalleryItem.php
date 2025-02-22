<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseGalleryItem;

#[ORM\Table(name: 'media__gallery_item')]
#[ORM\Entity]
class SonataMediaGalleryItem extends BaseGalleryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected $id;

    #[\Override]
    public function getId(): int|string|null
    {
        return $this->id;
    }
}
