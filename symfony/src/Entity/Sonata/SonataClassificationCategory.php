<?php
declare(strict_types=1);
namespace App\Entity\Sonata;

use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseCategory;

#[ORM\Table(name: 'classification__category')]
#[ORM\Entity]
class SonataClassificationCategory extends BaseCategory
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
