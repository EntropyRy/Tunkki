<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface as RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;

/**
 * @extends AbstractAdmin<object>
 */
final class EmailAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'email';
    }
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('purpose')
            ->add('event')
            ->add('subject')
            ->add('body')
            ->add('sentAt')
            ->add('sentBy')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        if (!$this->isChild()) {
            $listMapper
                ->add('event')
                ->addIdentifier('purpose');
        } else {
            $listMapper
                ->addIdentifier('purpose');
        }
        $listMapper
            ->add('subject')
            // ->add('body', 'html')
            ->add('updatedAt', 'datetime')
            ->add('sentAt', 'datetime')
            ->add('sentBy')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'preview' => ['template' => 'admin/crud/list__action_email_preview.html.twig'],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        if (!$this->isChild()) {
            $formMapper
                ->add('purpose', ChoiceType::class, [
                    'choices' => [
                        'Automatic email to new Member on registration (There should only be one)' => 'member',
                        'Automatic thank you email to member who requests Active Member status (There should only be one)' => 'active_member',
                        'New Active Member info package (can be sent from the member list) (There should only be one)' => 'active_member_info_package',
                        'Email to All VJs in our roster, meaming the VJs who have artist profile in our site' => 'vj_roster',
                        'Email to All DJs in our roster, meaming the DJs who have artist profile in our site' => 'dj_roster',
                        'Tiedotus (all members on the site, including active members)' => 'tiedotus',
                        'Aktiivit (all active members)' => 'aktiivit'
                        //'Other' => 'other'
                    ],
                    'required' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'help' => 'There is also automatic Booking email to vuokra list and "application rejected" for active member (sent from member list). these cannot be edited here. Other kinds of emails can be defined.'
                ]);
        } else {
            $formMapper
                ->add('purpose', ChoiceType::class, [
                    'choices' => [
                        'Can be sent now' => [
                            'To RSVP' => 'rsvp',
                            'To reserved and paid tickets holders' => 'ticket',
                            'To people who have reserved Nakki' => 'nakkikone',
                            'To all artists' => 'artist',
                            'Tiedotus (all members on the site, including active members)' => 'tiedotus',
                            'Aktiivit (all active members)' => 'aktiivit'
                        ],
                        'Sent as part of User action' => [
                            'To Stiripe tickets buyers. QR code email' => 'ticket_qr',
                        ]
                    ],
                    'required' => false,
                    'expanded' => false,
                    'multiple' => false,
                ])
                ->add('replyTo', null, [
                    'help' => 'Empty defaults to hallitus@entropy.fi.'
                ]);
        }
        $subjectHelp = 'All mails have forced prefix "[Entropy]" for consistency. Include Finnish and English version to same message!';
        $email = $this->getSubject();
        $disabled = false;
        $placeholder = $this->getSubject()->getSubject();
        if ($email != null && $email->getPurpose() == 'ticket_qr') {
            $subjectHelp = 'Generated automatically';
            $disabled = true;
            $placeholder = '[event name] Ticket #1 / Lippusi #1';
        }
        $formMapper
            ->add('subject', null, [
                'help' => $subjectHelp,
                'disabled' => $disabled,
                'data' => $placeholder
            ])
            ->add('body', SimpleFormatterType::class, ['format' => 'richhtml'])
            ->add('addLoginLinksToFooter', null, ['help' => 'adds links to login']);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('purpose')
            ->add('subject')
            ->add('body', 'html')
            ->add('addLoginLinksToFooter')
            ->add('createdAt')
            ->add('updatedAt');
    }

    #[\Override]
    protected function configureRoutes(RouteCollection $collection): void
    {
        $collection->remove('show');
        $collection->add('preview', $this->getRouterIdParameter() . '/preview');
        $collection->add('send', $this->getRouterIdParameter() . '/send');
        $collection->add('send_progress', $this->getRouterIdParameter() . '/send-progress');
    }
}
