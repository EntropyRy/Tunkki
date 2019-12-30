<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\Form\Type\DatePickerType;

final class MemberAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'createdAt',
    ];

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('username')
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
            ->add('user', null, ['label' => 'Tunkki User'])
            ->add('name')
            ->add('email')
            ->add('StudentUnionMember', null, ['editable' => true])
            ->add('copiedAsUser')
            ->add('isActiveMember')
            ->add('ApplicationDate')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                    'makeuser' => [
                        'template' => 'admin/crud/list__action_makeuser.html.twig'
                    ],
                    'sendrejectreason' => [
                        'template' => 'admin/crud/list__action_sendrejectreason.html.twig'
                    ],
                    'activememberinfo' => [
                        'template' => 'admin/crud/list__action_email_active_member_info.html.twig'
                    ],
                ],
            ])
        ;
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('username')
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
            ->add('username')
            ->add('name')
            ->add('email')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('StudentUnionMember')
            ->add('Application')
            ->add('ApplicationDate')
            ->add('ApplicationHandledDate')
            ->add('isActiveMember')
            ->add('rejectReason', null, ['help' => 'This field is an email to the member in which we explain why they were rejected. After this has been added the email can be sent from the member list'])
            ->add('rejectReasonSent')
            ->add('copiedAsUser')
            ->add('user')
            ->add('createdAt')
            ->add('updatedAt')
        ;
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('makeuser', $this->getRouterIdParameter().'/makeuser');
        $collection->add('sendrejectreason', $this->getRouterIdParameter().'/sendrejectreason');
        $collection->add('activememberinfo', $this->getRouterIdParameter().'/activememberinfo');
    }   
    public function postUpdate($member)
    {
        $user = $member->getUser();
        if($user){
            $member->setUsername($user->getUsername());
        }
    }
}
