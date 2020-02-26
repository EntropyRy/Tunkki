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
use Sonata\DoctrineORMAdminBundle\Filter\DateRangeFilter;
use Sonata\Form\Type\DateRangeType;

final class MemberAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'member';
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
            ->add('copiedAsUser')
            ->add('StudentUnionMember')
            ->add('isActiveMember')
            ->add('isFullMember')
            ->add('ApplicationHandledDate', DateRangeFilter::class, ['field_type' => DateRangeType::class])
            ->add('AcceptedAsHonoraryMember', DateRangeFilter::class, ['field_type' => DateRangeType::class])
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('user', null, ['label' => 'Tunkki User'])
            ->add('name')
            ->add('email')
            ->add('StudentUnionMember', null, ['editable' => true])
            ->add('isActiveMember')
            ->add('isFullMember')
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
        $editable = false;
        $object = $this->getSubject();
        if ($object and !is_null($object->getApplication())){
            $editable = true;
        }
        $formMapper
            ->with('Base',['class' => 'col-md-4'])
            ->add('firstname')
            ->add('lastname')
            ->add('email')
            ->add('phone')
            ->add('CityOfResidence')
            ->end()
            ->with('Membership status',['class' => 'col-md-4'])
            ->add('StudentUnionMember', null, ['help' => 'Everyone who is this is actual member of entropy with voting rights'])
            ->add('isActiveMember',null, ['help' => 'Grants access to Entropy systems'])
            ->add('isFullMember',null, ['help' => 'Regardless of Student union membership this grants voting rights and access to Entropy systems'])
            ->add('AcceptedAsHonoraryMember', DatePickerType::class, [
                'required' => false,
                'help' => 'Grants free access to Entropy parties'
            ])
            ->end()
            ->with('Membership info',['class' => 'col-md-4'])
            ->add('username', null, ['help' => 'asked from the member'])
            ->add('Application', null, ['disabled' => $editable])
            ->add('ApplicationDate', DatePickerType::class, ['required' => false])
            ->add('ApplicationHandledDate', DatePickerType::class, [
                'required' => false, 
                'help'=>'doubles as accepted as active member date'
            ])
            ->add('copiedAsUser')
            ->add('user', null, ['help' => 'Tunkki User', 'disabled' => true])
            ->end()
            ;
        //if (is_null($this->getSubject()->getApplicationHandledDate())){
            $formMapper
                ->with('Membership info')
                ->add('rejectReason', null, ['help' => 'This field is an email to the member in which we explain why they were rejected. After this has been added the email can be sent from the member list'])
                ->add('rejectReasonSent')
                ->end()
                ;
        //}
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
            ->add('isFullMember')
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
    public function getExportFields()
    {
        return ['name', 'email', 'StudentUnionMember', 'isActiveMember', 'AcceptedAsHonoraryMember'];
    }
}
