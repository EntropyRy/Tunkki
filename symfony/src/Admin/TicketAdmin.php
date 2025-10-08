<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Ticket;
use App\Helper\ReferenceNumber;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractAdmin<Ticket>
 */
final class TicketAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'ticket';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        if (!$this->isChild()) {
            $filter
                ->add('event');
        }
        $filter
            ->add('name')
            ->add('price')
            ->add('owner')
            ->add('email')
            ->add('given')
            ->add('referenceNumber')
            ->add('status', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'multiple' => true,
                    'choices' => [
                        'available' => 'available',
                        'reserved' => 'reserved',
                        'paid' => 'paid',
                        'paid_with_bus' => 'paid_with_bus',
                    ],
                ],
            ])
            ->add('updatedAt');
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        if (!$this->isChild()) {
            $list
                ->add('event');
        }
        $list
            ->add('ticketHolderHasNakki')
            ->add('name')
            ->add('stripeProductId')
            ->add('price')
            ->add('given', null, ['editable' => true])
            ->add('owner.firstname')
            ->add('owner.lastname')
            ->add('email')
            ->add('referenceNumber')
            ->add('status')
            ->add('updatedAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'changeOwner' => [
                        'template' => 'admin/ticket/button_change_owner.html.twig',
                    ],
                    'give' => [
                        'template' => 'admin/ticket/button_give.html.twig',
                    ],
                    'sendQrCodeEmail' => [
                        'template' => 'admin/ticket/button_send_qr_code_email.html.twig',
                    ],
                    'edit' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        if (!$this->isChild()) {
            $form
                ->add('event');
        }
        $form
            ->add('name')
            ->add('price')
            ->add('given')
            ->add('email')
            ->add('owner')
            ->add('referenceNumber', null, ['disabled' => true])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'available' => 'available',
                    'reserved' => 'reserved',
                    'paid' => 'paid',
                    'paid_with_bus' => 'paid_with_bus',
                ],
            ]);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('price')
            ->add('event')
            ->add('owner')
            ->add('email')
            ->add('referenceNumber')
            ->add('status')
            ->add('updatedAt');
    }

    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('show');
        $collection->add('updateTicketCount', 'countupdate');
        $collection->add('give', $this->getRouterIdParameter().'/give');
        $collection->add('makePaid', $this->getRouterIdParameter().'/bought');
        $collection->add('addBus', $this->getRouterIdParameter().'/bus');
        $collection->add('changeOwner', $this->getRouterIdParameter().'/change');
        $collection->add('sendQrCodeEmail', $this->getRouterIdParameter().'/send-qr-code-email');
    }

    #[\Override]
    public function postPersist($object): void
    {
        if (null == $object->getReferenceNumber()) {
            $object->setReferenceNumber($this->rn->calculateReferenceNumber($object, 9000, 909));
            $this->update($object);
        }
    }

    public function __construct(
        protected ReferenceNumber $rn,
    ) {
    }
}
