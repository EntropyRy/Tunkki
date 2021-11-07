<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

final class RSVPAdmin extends AbstractAdmin
{

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('firstName')
            ->add('lastName')
            ->add('email')
            ->add('member');
        if(!$this->isChild()){
            $filter
                ->add('event');
        }
        $filter
            ->add('createdAt')
            ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
            ->add('email')
            ->add('member');
        if(!$this->isChild()){
            $list
                ->add('event');
        }
        $list
            ->add('createdAt')
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
            ->add('email')
            ->add('member');
        if(!$this->isChild()){
            $form
                ->add('event');
        }
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('member')
            ->add('event')
            ->add('createdAt')
            ;
    }
}
