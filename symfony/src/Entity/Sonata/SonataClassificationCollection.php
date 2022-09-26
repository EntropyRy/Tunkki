<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseCollection;

#[ORM\Table(name: 'classification__collection')]
#[ORM\Entity]
class SonataClassificationCollection extends BaseCollection
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
