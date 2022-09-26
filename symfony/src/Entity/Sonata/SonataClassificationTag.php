<?php
namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseTag;

#[ORM\Table(name: 'classification__tag')]
#[ORM\Entity]
class SonataClassificationTag extends BaseTag
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
