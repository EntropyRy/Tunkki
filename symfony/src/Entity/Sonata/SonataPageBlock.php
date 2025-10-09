<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BaseBlock;

#[ORM\Table(name: 'page__block')]
#[ORM\Entity]
class SonataPageBlock extends BaseBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected $id;
}
