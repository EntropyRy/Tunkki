<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;

final class NakkiBookingAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $list
            ->add('nakki');
        if (!$this->isChild()) {
            $filter
                ->add('event');
        }
        $filter
            ->add('member')
            ->add('startAt')
            ->add('endAt');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('nakki');
        if (!$this->isChild()) {
            $list
                ->add('event');
        }
        $list
            ->add('member')
            ->add('startAt')
            ->add('endAt')
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
            ->add('nakki');
        if (!$this->isChild()) {
            $form
                ->add('event');
        }
        $form
            ->add('member')
            ->add('startAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
            ])
            ->add('endAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
            ]);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('nakki')
            ->add('event')
            ->add('member')
            ->add('startAt')
            ->add('endAt');
    }
}
