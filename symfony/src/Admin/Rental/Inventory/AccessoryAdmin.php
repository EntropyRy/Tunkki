<?php

declare(strict_types=1);

namespace App\Admin\Rental\Inventory;

use App\Admin\Rental\AbstractRentalAdmin;
use App\Entity\Rental\Inventory\Accessory;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractRentalAdmin<Accessory>
 */
class AccessoryAdmin extends AbstractRentalAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(
        bool $isChildAdmin = false,
    ): string {
        return 'accessory';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('name')
            ->add('count')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('count')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []]])
        ;
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('name')
            ->add('count')
        ;
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('name')
            ->add('count')
        ;
    }
}
