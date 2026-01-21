<?php

declare(strict_types=1);

namespace App\Admin\Rental\Inventory;

use App\Entity\Rental\Inventory\AccessoryChoice;
use App\Admin\Rental\AbstractRentalAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractRentalAdmin<AccessoryChoice>
 */
class AccessoryChoiceAdmin extends AbstractRentalAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(
        bool $isChildAdmin = false,
    ): string {
        return 'accessory-choice';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
//            ->add('id')
            ->add('name')
            ->add('compensationPrice', null, ['label' => 'Compensation price (€)'])
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('compensationPrice', null, ['label' => 'Compensation price (€)'])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []]])
        ;
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('name')
            ->add('compensationPrice', null, ['label' => 'Compensation price (€)'])
        ;
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('name')
            ->add('compensationPrice', null, ['label' => 'Compensation price (€)'])
        ;
    }
}
