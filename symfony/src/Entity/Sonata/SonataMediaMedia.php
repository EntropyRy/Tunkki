<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\MediaBundle\Entity\BaseMedia;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'media__media')]
#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'media')]
class SonataMediaMedia extends BaseMedia
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    // Override parent properties to disable NotNull validation
    #[Assert\DisableAutoMapping]
    protected ?\DateTimeInterface $createdAt = null;

    #[Assert\DisableAutoMapping]
    protected ?\DateTimeInterface $updatedAt = null;

    #[Assert\DisableAutoMapping]
    protected ?int $size = null;

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }
}
