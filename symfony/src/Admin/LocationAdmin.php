<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

final class LocationAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('name')
            ->add('latitude')
            ->add('longitude')
            ->add('streetAddress');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
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

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('name')
            ->add('latitude', null, [
                'help' => 'in Helsinki this is something like 60.???'
            ])
            ->add('longitude')
            ->add('streetAddress');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('name')
            ->add('latitude')
            ->add('longitude')
            ->add('streetAddress');
    }
}
