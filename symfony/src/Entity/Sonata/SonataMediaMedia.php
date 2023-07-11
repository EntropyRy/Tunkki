<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseMedia;

#[ORM\Table(name: 'media__media')]
#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'media')]
#[ORM\HasLifecycleCallbacks]
class SonataMediaMedia extends BaseMedia
{
    /**
     * @var int
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    public function getId(): int|string|null
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        parent::prePersist();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        parent::preUpdate();
    }
}
