<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;

final class DoorLogAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'doorlog';

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('member')
            ->add('createdAt')
            ->add('message')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('member')
            ->add('createdAt')
            ->add('message')
/*            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                ],
])*/;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('createdAt')
            ->add('message')
        ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('user')
            ->add('createdAt')
            ->add('message')
        ;
    }
    public function configureRoutes(\Sonata\AdminBundle\Route\RouteCollection $collection): void
    {
        $collection->remove('edit');
        $collection->remove('delete');
        $collection->remove('create');
    }
}
