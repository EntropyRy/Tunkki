<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

final class NakkiDefinitionAdmin extends AbstractAdmin
{

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
            ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
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
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
            ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
            ;
    }
}
