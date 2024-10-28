<?php

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseContext;

#[ORM\Entity]
#[ORM\Table(name: 'classification__context')]
class SonataClassificationContext extends BaseContext
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    protected ?string $id = null;
}
