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
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'rsvp';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('firstName')
            ->add('lastName')
            ->add('email')
            ->add('member');
        if (!$this->isChild()) {
            $filter
                ->add('event');
        }
        $filter
            ->add('createdAt');
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
            ->add('email')
            ->add('member');
        if (!$this->isChild()) {
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

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('firstName')
            ->add('lastName')
            ->add('email')
            ->add('member');
        if (!$this->isChild()) {
            $form
                ->add('event');
        }
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('member')
            ->add('event')
            ->add('createdAt');
    }
}
