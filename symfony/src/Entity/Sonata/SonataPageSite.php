<?php

declare(strict_types=1);

namespace App\Entity\Sonata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\PageBundle\Entity\BaseSite;

/**
 * @method string      getHost()
 * @method self        setHost(string $host)
 * @method string      getLocale()
 * @method self        setLocale(string $locale)
 * @method string|null getRelativePath()
 * @method self        setRelativePath(?string $path)
 * @method bool        isDefault()
 * @method bool        getIsDefault()
 * @method self        setIsDefault(bool $default)
 * @method bool        isEnabled()
 * @method bool        getEnabled()
 * @method self        setEnabled(bool $enabled)
 * @method self        setEnabledFrom(\DateTimeInterface $from)
 * @method self        setEnabledTo(?\DateTimeInterface $to)
 * @method string      getName()
 * @method self        setName(string $name)
 */
#[ORM\Table(name: 'page__site')]
#[ORM\Entity]
class SonataPageSite extends BaseSite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected $id;
}
