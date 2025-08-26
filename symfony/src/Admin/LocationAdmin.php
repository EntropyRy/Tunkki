<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<object>
 */
final class LocationAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('name')
            ->add('nameEn')
            ->add('latitude')
            ->add('longitude')
            ->add('streetAddress');
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
            ->add('nameEn', null, ['label' => 'Name (EN)'])
            ->add('latitude')
            ->add('longitude')
            ->add('streetAddress')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('name')
            ->add('nameEn', null, [
                'required' => false,
                'label' => 'Name (EN)',
            ])
            ->add('latitude', null, [
                'help' => 'in Helsinki this is something like 60.???',
                'required' => true,
            ])
            ->add('longitude', null, [
                'required' => true,
            ])
            ->add('streetAddress');
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('name')
            ->add('nameEn', null, ['label' => 'Name (EN)'])
            ->add('latitude')
            ->add('longitude')
            ->add('streetAddress');
    }
}
