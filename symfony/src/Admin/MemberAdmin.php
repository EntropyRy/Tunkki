<?php

declare(strict_types=1);

namespace App\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\DateRangeFilter;
use Sonata\Form\Type\DateRangeType;
use Sonata\Form\Type\DatePickerType;
use App\Helper\Mattermost;

final class MemberAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'member';
    }

    #[\Override]
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        // display the first page (default = 1)
        $sortValues[DatagridInterface::PAGE] = 1;

        // reverse order (default = 'ASC')
        $sortValues[DatagridInterface::SORT_ORDER] = 'DESC';

        // name of the ordered field (default = the model's id field, if any)
        $sortValues[DatagridInterface::SORT_BY] = 'createdAt';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $now = new \DateTime();
        $datagridMapper
            ->add('artist')
            ->add('username')
            ->add('firstname')
            ->add('lastname')
            ->add('email')
            ->add('emailVerified')
            ->add('allowInfoMails')
            ->add('allowActiveMemberMails')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('ApplicationDate', DateRangeFilter::class, ['field_type' => DateRangeType::class])
            ->add(
                'ApplicationHandledDate',
                DateRangeFilter::class,
                [
                    'field_type' => DateRangeType::class,
                    'field_options' => [
                        'field_options_start' => [
                            'years' => range(1993, $now->format('Y'))
                        ],
                        'field_options_end' => [
                            'years' => range(1993, $now->format('Y'))
                        ],
                    ]
                ]
            )
            ->add('StudentUnionMember')
            ->add('isActiveMember')
            ->add('isFullMember')
            ->add(
                'AcceptedAsHonoraryMember',
                DateRangeFilter::class,
                [
                    'field_type' => DateRangeType::class,
                    'field_options' => [
                        'field_options_start' => [
                            'years' => range(1993, $now->format('Y'))
                        ],
                        'field_options_end' => [
                            'years' => range(1993, $now->format('Y'))
                        ],
                    ]
                ]
            )
            //->add('user.CreatedAt', DateRangeFilter::class, ['field_type' => DateRangeType::class])
            ->add('createdAt', DateRangeFilter::class, ['field_type' => DateRangeType::class]);
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('artist')
            ->add('name')
            ->add('email')
            ->add('emailVerified', null, ['editable' => true])
            ->add('StudentUnionMember', null, ['editable' => true])
            ->add('isActiveMember')
            ->add('isFullMember')
            ->add('user.LastLogin')
            ->add(
                ListMapper::NAME_ACTIONS,
                null,
                [
                    'actions' => [
                        'show' => [],
                        'edit' => [],
                        'delete' => [],
                        /*'makeuser' => [
                        'template' => 'admin/crud/list__action_makeuser.html.twig'
                    ],
                    'sendrejectreason' => [
                        'template' => 'admin/crud/list__action_sendrejectreason.html.twig'
                    ], */
                        'activememberinfo' => [
                            'template' => 'admin/crud/list__action_email_active_member_info.html.twig'
                        ],
                    ],
                ]
            );
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $editable = false;
        $object = $this->getSubject();
        if ($object != null && !empty($object->getApplication())) {
            $editable = true;
        }
        $formMapper
            ->with('Base', ['class' => 'col-md-4'])
            ->add('artist', null, ['disabled' => true])
            ->add('username')
            ->add('firstname')
            ->add('lastname')
            ->add('email')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('locale')
            // ->add('user.MattermostId')
            ->end()
            ->with('Email settings', ['class' => 'col-md-4'])
            ->add('emailVerified', null, ['help' => 'Email verified'])
            ->add('allowInfoMails')
            ->add('allowActiveMemberMails')
            ->end()
            ->with('Membership status', ['class' => 'col-md-4'])
            ->add('StudentUnionMember', null, ['help' => 'Everyone who is this is actual member of entropy with voting rights'])
            ->add('isActiveMember', null, ['help' => 'Next: Send the active member mail from the memberlist, add to aktiivit-mailinglist and add to aktiivit group in forums'])
            ->add('denyKerdeAccess', null, ['help' => 'Denies access to Entropy Kerde'])
            ->add('isFullMember', null, ['help' => 'Regardless of Student union membership this grants voting rights and access to Entropy systems'])
            ->add(
                'AcceptedAsHonoraryMember',
                DatePickerType::class,
                [
                    'required' => false,
                    'help' => 'Grants free access to Entropy parties'
                ]
            )
            //->add('user.accessGroups', ChoiceType::class, ['disabled' => true, 'multiple' => true ,'dd'=>''])
            ->end()
            ->with('Membership info', ['class' => 'col-md-4'])
            ->add('Application', null, ['disabled' => $editable])
            ->add('ApplicationDate', DatePickerType::class, ['required' => false])
            ->add(
                'ApplicationHandledDate',
                DatePickerType::class,
                [
                    'required' => false,
                    'help' => 'doubles as accepted as active member date'
                ]
            )
            ->add('user', null, ['help' => 'Tunkki User', 'disabled' => true])
            ->end();
        //if (is_null($this->getSubject()->getApplicationHandledDate())){
        $formMapper
            ->with('Membership info')
            ->add('rejectReason', null, ['help' => 'This field is an email to the member in which we explain why they were rejected. After this has been added the email can be sent from the member list'])
            ->add('rejectReasonSent')
            ->end();
        //}
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('username')
            ->add('user.MattermostId')
            ->add('name')
            ->add('email')
            ->add('emailVerified')
            ->add('allowInfoMails')
            ->add('allowActiveMemberMails')
            ->add('phone')
            ->add('CityOfResidence')
            ->add('StudentUnionMember')
            ->add('Application')
            ->add('ApplicationDate')
            ->add('ApplicationHandledDate')
            ->add('isActiveMember')
            ->add('isFullMember')
            ->add(
                'rejectReason',
                null,
                [
                    'help' => 'This field is an email to the member in which we explain why they were rejected. After this has been added the email can be sent from the member list'
                ]
            )
            ->add('rejectReasonSent')
            ->add('user')
            ->add('createdAt')
            ->add('updatedAt');
    }
    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('activememberinfo', $this->getRouterIdParameter() . '/activememberinfo');
    }
    #[\Override]
    public function configureExportFields(): array
    {
        return ['name', 'email', 'StudentUnionMember', 'isActiveMember', 'isFullMember', 'AcceptedAsHonoraryMember'];
    }
    #[\Override]
    public function preRemove($member): void
    {
        foreach ($member->getArtist() as $artist) {
            foreach ($artist->getEventArtistInfos() as $info) {
                $info->removeArtist();
            }
            $this->em->persist($artist);
            $this->em->remove($artist);
        }
        $this->em->flush();
    }
    #[\Override]
    public function postRemove($member): void
    {
        $text = '**Member deleted: ' . $member . '**';
        $this->mm->SendToMattermost($text, 'yhdistys');
    }
    public function __construct(protected Mattermost $mm, protected EntityManagerInterface $em)
    {
    }
}
