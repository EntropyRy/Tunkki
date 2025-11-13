<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        if (
            'panther' === $this->environment
            && ($custom = getenv('PANTHER_CACHE_DIR') ?: ($_SERVER['PANTHER_CACHE_DIR'] ?? null))
        ) {
            return $custom;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if (
            'panther' === $this->environment
            && ($custom = getenv('PANTHER_LOG_DIR') ?: ($_SERVER['PANTHER_LOG_DIR'] ?? null))
        ) {
            return $custom;
        }

        return parent::getLogDir();
    }
}
