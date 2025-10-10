<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BasePage;

/**
 * @property string|null $routeName
 * @property bool $enabled
 * @property string|null $pageAlias
 */
#[ORM\Table(name: 'page__page')]
#[ORM\Entity]
class SonataPagePage extends BasePage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected $id;
}
