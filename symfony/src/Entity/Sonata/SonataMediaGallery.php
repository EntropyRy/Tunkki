<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseGallery;

#[ORM\Table(name: 'media__gallery')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class SonataMediaGallery extends BaseGallery
{
    /**
     * @var int
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[\Override]
    public function getId(): int|string|null
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    #[\Override]
    public function prePersist(): void
    {
        parent::prePersist();
    }

    #[ORM\PreUpdate]
    #[\Override]
    public function preUpdate(): void
    {
        parent::preUpdate();
    }
}
