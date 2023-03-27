<?php

namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseContext;
use Doctrine\DBAL\Types\Types;

#[ORM\Table(name: 'classification__context')]
#[ORM\Entity]
class SonataClassificationContext extends BaseContext
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected ?string $id = null;
}
