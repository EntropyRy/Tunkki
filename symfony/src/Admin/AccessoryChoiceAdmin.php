<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\AccessoryChoice;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<AccessoryChoice>
 */
class AccessoryChoiceAdmin extends AbstractAdmin
{
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
