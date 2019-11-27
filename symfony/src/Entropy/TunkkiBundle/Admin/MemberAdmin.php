<?php

declare(strict_types=1);

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\Form\Type\DatePickerType;

final class MemberAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('firstname')
            ->add('lastname')
            ->add('email')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('StudentUnionMember')
            ->add('copiedAsUser')
            ->add('isActiveMember')
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('email')
            ->add('StudentUnionMember')
            ->add('copiedAsUser')
            ->add('isActiveMember')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                    'makeuser' => [
                        'template' => 'EntropyTunkkiBundle:CRUD:list__action_makeuser.html.twig'
                    ],
                    'sendrejectreason' => [
                        'template' => 'EntropyTunkkiBundle:CRUD:list__action_sendrejectreason.html.twig'
                    ],
                ],
            ])
        ;
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('firstname')
            ->add('lastname')
            ->add('email')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('StudentUnionMember')
            ->add('Application')
            ->add('ApplicationDate', DatePickerType::class, ['required' => false])
            ->add('ApplicationHandledDate', DatePickerType::class, ['required' => false])
            ->add('rejectReason')
            ->add('rejectReasonSent')
            ->add('isActiveMember')
            ->add('copiedAsUser')
        ;
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('name')
            ->add('email')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('StudentUnionMember')
            ->add('Application')
            ->add('ApplicationDate')
            ->add('ApplicationHandledDate')
            ->add('isActiveMember')
            ->add('rejectReason')
            ->add('rejectReasonSent')
            ->add('copiedAsUser')
            ->add('createdAt')
            ->add('updatedAt')
        ;
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('makeuser', $this->getRouterIdParameter().'/makeuser');
        $collection->add('sendrejectreason', $this->getRouterIdParameter().'/sendrejectreason');
    }   
}
