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
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\Form\Type\ImmutableArrayType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\UrlsType;

final class EventAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'event';
    protected $datagridValues = [
        '_sort_order' => 'DESC',
        '_sort_by' => 'EventDate',
    ];

    protected function configureSideMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null)
    {
       if (!$childAdmin && !in_array($action, array('edit', 'show'))) {
           return;
       }
       $admin = $this->isChild() ? $this->getParent() : $this;
       $id = $admin->getRequest()->get('id');

       if ($this->isGranted('EDIT')) {
			$menu->addChild('Event', 
               $admin->generateMenuUrl('edit', ['id' => $id])
            );
            $menu->addChild('Artist editor', 
               $admin->generateMenuUrl('admin.event_artist_info.list', ['id' => $id])
            );
            $menu->addChild('Artist list', [
               'uri' => $admin->generateUrl('artistList', ['id' => $id])
            ]);
            if ($admin->getSubject()->getRsvpSystemEnabled()){
                $menu->addChild('RSVPs', [
                   'uri' => $admin->generateUrl('rsvp', ['id' => $id])
                ]);
            }
            $menu->addChild('Nakkikone', [
               'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.nakki.list', ['id' => $id])
            ]);
            $menu->addChild('Nakit', [
               'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.nakki_booking.list', ['id' => $id])
            ]);
            $menu->addChild('Preview', [
                'route' => 'entropy_event',
                'routeParameters' => [
                    'id'=> $id,
                 ],
                 'linkAttributes' => ['target' => '_blank']
            ]);
       }
    }
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $TypeChoices = [
            'Event' => 'event',
            'Clubroom Event' => 'clubroom',
            'Announcement' => 'announcement',
        ];
        $datagridMapper
            ->add('Name')
            ->add('Content')
            ->add('Nimi')
            ->add('Sisallys')
            ->add('type',null,[],ChoiceType::class, ['choices' => $TypeChoices])
            ->add('EventDate')
            ->add('publishDate')
            ->add('css')
            ->add('url')
            ->add('sticky')
            ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('EventDate')
            ->addIdentifier('Name')
            ->add('type')
            ->add('publishDate')
            ->add('externalUrl')
            ->add('url')
            ->add('sticky')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $TypeChoices = [
            'Event' => 'event',
            'Clubroom Event' => 'clubroom',
            'Announcement' => 'announcement',
        ];
        $PicChoices = [
            'Banner' => 'banner',
            'Right side of the text' => 'right',
            'After the post' => 'after',
        ];
        if ($this->isCurrentRoute('create')) {
            $formMapper
                // The thumbnail field will only be added when the edited item is created
                ->with('English', ['class' => 'col-md-6'])
                ->add('Name')
                ->end()
                ->with('Finnish', ['class' => 'col-md-6'])
                ->add('Nimi')
                ->end()
                ->with('Functionality', ['class' => 'col-md-4'])
                ->add('type', ChoiceType::class, ['choices' => $TypeChoices])
                ->add('EventDate', DateTimePickerType::class, ['label' => 'Event Date and Time'])
                ->add('until', DateTimePickerType::class, ['label' => 'Event stop time', 'required' => false])
                ->add('published', null, ['help' => 'Only logged in users can see if not published'])
                ->add('publishDate', DateTimePickerType::class, [
                    'help' => 'If this needs to be released at certain time',
                    'required' => false
                    ]
                )
                ->add('externalUrl', null, [
                    'label' => 'Is the advertisement hosted somewhere else? if this is selected and url is empty it can be used to have the event in events list.',
                    'help'=>'Is the add hosted here?'
                ])
                ->add('url', null, [
                    'help' => '\'event\' resolves to https://entropy.fi/(year)/event. In case of external need whole url like: https://entropy.fi/rave/bunka1'])
                ->end();
        } else {
            $event = $this->getSubject();
            //if($event->getType() == 'announcement'){}
            $formMapper
                ->tab('Event')
                ->with('English', ['class' => 'col-md-6'])
                ->add('Name');
            if ($event->getexternalUrl()==false){
                $formMapper
                    ->add('Content', SimpleFormatterType::class, [
                        'format' => 'richhtml', 
                        'required' => false,
                        'ckeditor_context' => 'default',
                        'help' => 'use special tags {{ timetable }}, {{ bios }}, {{ vj_bios }}, {{ rsvp }}, {{ links }} as needed.'
                    ]);
            }
            $formMapper
                ->end()
                ->with('Finnish', ['class' => 'col-md-6'])
                ->add('Nimi');
            if ($event->getexternalUrl()==false){
                $formMapper
                    ->add('Sisallys', SimpleFormatterType::class, [
                        'format' => 'richhtml', 
                        'required' => false,
                        'ckeditor_context' => 'default', 
                        'help' => 'käytä erikoista tagejä {{ timetable }}, {{ bios }}, {{ vj_bios }}, {{ rsvp }}, {{ links }} niinkun on tarve.'
                    ]);
            }
            $formMapper
                ->end()
                ->with('Functionality', ['class' => 'col-md-4'])
                ->add('type', ChoiceType::class, ['choices' => $TypeChoices])
                ->add('cancelled', null, ['help' => 'Event has been cancelled'])
                ->add('EventDate', DateTimePickerType::class, ['label' => 'Event Date and Time'])
                ->add('until', DateTimePickerType::class, ['label' => 'Event stop time', 'required' => false])
                ->add('published', null, ['help' => 'Only logged in users can see if not published'])
                ->add('publishDate', DateTimePickerType::class, [
                    'help' => 'If this needs to be released at certain time',
                    'required' => false
                    ]
                )
                ->add('sticky', null, ['help' => 'Shown first on frontpage. There can only be one!'])
                ->add('rsvpSystemEnabled', null, ['help' => 'allow RSVP to the event'])
                ->add('externalUrl', null, [
                    'label' => 'External Url/No addvert at all if url is empty',
                    'help'=>'Is the add hosted here?'
                ])
                ->add('url', null, [
                    'help' => '\'event\' resolves to https://entropy.fi/(year)/event. 
                     In case of external need whole url like: https://entropy.fi/rave/bunka1'
                    ])
                ->end()
                ->with('Eye Candy', ['class' => 'col-md-8'])
                ->add('picture', ModelListType::class,[
                        'required' => false
                    ],[ 
                        'link_parameters'=>[
                        'context' => 'event'
                    ]])
                ->add('picturePosition', ChoiceType::class, ['choices' => $PicChoices]);
            if ($event->getexternalUrl()==false){
                $formMapper
                    ->add('css')
                    ->add('attachment', ModelListType::class,[
                        'required' => false,
                        'help' => 'added as downloadable link'
                        ],[ 
                            'link_parameters'=>[
                            'context' => 'event',
                            'provider' => 'sonata.media.provider.file'
                        ]]);
            }
            $formMapper
                ->add('epics', null, ['help' => 'link to ePics pictures'])
                ->add('links', ImmutableArrayType::class, [
                    'help' => 'Titles are translated automatically. examples: tickets, fb.event.<br> 
                                request admin to add more translations!',
                    'keys' => [
                        ['urls', CollectionType::class, [
                            'required' => false,
                            'allow_add' => true,
                            'allow_delete' => true,
                            'prototype' => true,
                            'by_reference' => false,
                            'allow_extra_fields' => true,
                            'entry_type' => UrlsType::class,
                        ]],
                    ]])
                ->end()
                ->end()
                ->tab('Nakkikone config')
                ->add('NakkikoneEnabled', null, [
                    'help' => 'Publish nakkikone and allow members to reserve Nakkis (links are added and reservation works)',
                ])
                    ->add('nakkiInfoEn', SimpleFormatterType::class, [
                        'format' => 'richhtml', 
                        'required' => false,
                        'ckeditor_context' => 'default', 
                    ])
                    ->add('nakkiInfoFi', SimpleFormatterType::class, [
                        'format' => 'richhtml', 
                        'required' => false,
                        'ckeditor_context' => 'default', 
                    ])
                ->end()
                ;
        }
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('Name')
            ->add('Nimi')
            ->add('EventDate')
            ->add('publishDate')
            ->add('publishPlaces')
            ->add('css')
            ->add('Content')
            ->add('Sisallys')
            ->add('url')
            ;
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('artistList', $this->getRouterIdParameter().'/artistlist');
        $collection->add('rsvp', $this->getRouterIdParameter().'/rsvp');
    }
}
