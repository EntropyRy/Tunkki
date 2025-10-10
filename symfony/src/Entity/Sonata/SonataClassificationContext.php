<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseContext;

#[ORM\Entity]
#[ORM\Table(name: 'classification__context')]
class SonataClassificationContext extends BaseContext
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 255)]
    /** @phpstan-ignore-next-line doctrine.columnType (Parent BaseContext requires ?string type; cannot override) */
    protected ?string $id = null;
}
