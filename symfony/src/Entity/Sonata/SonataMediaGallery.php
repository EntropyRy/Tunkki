<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseGallery;
use Sonata\MediaBundle\Model\GalleryItemInterface;

/**
 * @extends BaseGallery<GalleryItemInterface>
 */
#[ORM\Table(name: 'media__gallery')]
#[ORM\Entity]
class SonataMediaGallery extends BaseGallery
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }
}
