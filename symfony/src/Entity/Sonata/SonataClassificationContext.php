<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseContext;

#[ORM\Table(name: 'classification__context')]
#[ORM\Entity]
class SonataClassificationContext extends BaseContext
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?string $id;
    public function getId(): ?string
    {
        return $this->id;
    }
}
