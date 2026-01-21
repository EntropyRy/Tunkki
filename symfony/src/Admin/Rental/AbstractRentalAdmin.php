<?php

declare(strict_types=1);

namespace App\Admin\Rental;

use Sonata\AdminBundle\Admin\AbstractAdmin;

abstract class AbstractRentalAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return 'admin_'.str_replace('.', '_', $this->getCode());
    }
}
