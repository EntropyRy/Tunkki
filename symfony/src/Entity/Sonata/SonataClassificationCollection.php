<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseCollection;

#[ORM\Entity]
#[ORM\Table(name: 'classification__collection')]
class SonataClassificationCollection extends BaseCollection
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }
}
