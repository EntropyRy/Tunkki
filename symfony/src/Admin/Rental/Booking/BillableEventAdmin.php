<?php

declare(strict_types=1);

namespace App\Admin\Rental\Booking;

use App\Admin\Rental\AbstractRentalAdmin;
use App\Entity\Rental\Booking\BillableEvent;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractRentalAdmin<BillableEvent>
 */
class BillableEventAdmin extends AbstractRentalAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(
        bool $isChildAdmin = false,
    ): string {
        return 'billable-event';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('description')
            ->add('unitPrice')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('description')
            ->add('unitPrice')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []]])
        ;
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('description')
            ->add('unitPrice')
        ;
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('description')
            ->add('unitPrice')
        ;
    }
}
