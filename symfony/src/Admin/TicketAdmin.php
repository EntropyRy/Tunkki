<?php

declare(strict_types=1);

namespace App\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

final class TicketAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'ticket';
    protected $datagridValues = [
        '_sort_order' => 'DESC',
        '_sort_by' => 'id',
    ];

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('price')
            ->add('event')
            ->add('owner')
            ->add('recommendedBy')
            ->add('referenceNumber')
            ->add('status')
            ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('price')
            ->add('event')
            ->add('owner')
            ->add('recommendedBy')
            ->add('referenceNumber')
            ->add('status')
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
            ->add('price')
            ->add('event')
            ->add('owner')
            ->add('recommendedBy')
            ->add('referenceNumber')
            ->add('status')
            ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('price')
            ->add('event')
            ->add('owner')
            ->add('recommendedBy')
            ->add('referenceNumber')
            ->add('status')
            ;
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('updateTicketCount', 'countupdate');
    }
}
