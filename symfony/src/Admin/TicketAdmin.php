<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;

final class TicketAdmin extends AbstractAdmin
{
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'ticket';
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        if (!$this->isChild()) {
            $filter
                ->add('event');
        }
        $filter
            ->add('price')
            ->add('owner')
            ->add('recommendedBy')
            ->add('referenceNumber')
            ->add('status', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' =>
                [
                    'multiple' => true,
                    'choices' => [
                        'available' => 'available',
                        'reserved' => 'reserved',
                        'paid' => 'paid'
                    ]
                ]])
            ->add('updatedAt')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        if (!$this->isChild()) {
            $list
                ->add('event');
        }
        $list
            ->add('price')
            ->add('owner')
            ->add('recommendedBy')
            ->add('referenceNumber')
            ->add('status')
            ->add('updatedAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'makePaid' => [
                        'template' => 'admin/crud/list__action_make_ticket_paid.html.twig'
                    ],
                    'edit' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        if (!$this->isChild()) {
            $form
                ->add('event');
        }
        $form
            ->add('price')
            ->add('owner')
            ->add('referenceNumber', null, ['disabled' => true])
            ->add('recommendedBy')
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'available' => 'available',
                    'reserved' => 'reserved',
                    'paid' => 'paid'
                ]
            ])
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
            ->add('updatedAt')
        ;
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('delete');
        $collection->remove('show');
        $collection->add('updateTicketCount', 'countupdate');
        $collection->add('makePaid', $this->getRouterIdParameter().'/bought');
    }
}
