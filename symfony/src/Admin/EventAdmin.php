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
use Symfony\Component\Form\Extension\Core\Type\ColorType;
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
            if (count($this->getSubject()->getEventArtistInfos()) > 0 ){
                $menu->addChild('Artist list', [
                   'uri' => $admin->generateUrl('artistList', ['id' => $id])
                ]);
            }
            $menu->addChild('RSVPs', [
               'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.rsvp.list', ['id' => $id])
            ]);
            if ($admin->getSubject()->getRsvpSystemEnabled() || count($admin->getSubject()->getRSVPs()) > 0){
                $menu->addChild('RSVP List', [
                   'uri' => $admin->generateUrl('rsvp', ['id' => $id])
                ]);
            }
            $menu->addChild('Nakkikone', [
               'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.nakki.list', ['id' => $id])
            ]);
            $menu->addChild('Nakit', [
               'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.nakki_booking.list', ['id' => $id])
            ]);
            if($this->getSubject()->getNakkikoneEnabled()){
                $menu->addChild('Printable Nakkilist', [
                   'uri' => $admin->generateUrl('nakkiList', ['id' => $id])
                ]);
            }
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
            'Stream' => 'stream',
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
                ->add('published', null, ['help' => 'The addvert will be available when the publish date has been reached otherwise not'])
                ->add('publishDate', DateTimePickerType::class, [
                    'help' => 'Select date and time for this to be published if it is in the future you should have published on.',
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
                        'help' => 'use special tags {{ streamplayer }}, {{ timetable }}, {{ bios }}, {{ vj_bios }}, {{ rsvp }}, {{ links }} as needed.'
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
                        'help' => 'käytä erikoista tagejä {{ streamplayer }}, {{ timetable }}, {{ bios }}, {{ vj_bios }}, {{ rsvp }}, {{ links }} niinkun on tarve.'
                    ]);
            }
            $formMapper
                ->end()
                ->with('Functionality', ['class' => 'col-md-4'])
                ->add('type', ChoiceType::class, ['choices' => $TypeChoices])
                ->add('cancelled', null, ['help' => 'Event has been cancelled'])
                ->add('EventDate', DateTimePickerType::class, ['label' => 'Event Date and Time'])
                ->add('until', DateTimePickerType::class, ['label' => 'Event stop time', 'required' => false])
                ->add('published', null, ['help' => 'The addvert will be available when the publish date has been reached otherwise not'])
                ->add('publishDate', DateTimePickerType::class, [
                    'help' => 'Select date and time for this to be published if it is in the future you should have published on.',
                    'required' => false
                    ]
                )
                ->add('externalUrl', null, [
                    'label' => 'External Url/No addvert at all if url is empty',
                    'help'=>'Is the add hosted here?'
                ])
                ->add('url', null, [
                    'help' => '\'event\' resolves to https://entropy.fi/(year)/event. 
                     In case of external need whole url like: https://entropy.fi/rave/bunka1'
                    ])
                ->add('streamPlayerUrl', null, [
                    'help' => 'use {{ streamplayer }} in content. Applies the player in the advert when the event is happening.'
                ])
                ->add('sticky', null, ['help' => 'Shown first on frontpage. There can only be one!'])
                ->end()
                ->with('Eye Candy', ['class' => 'col-md-4'])
                ->add('picture', ModelListType::class,[
                        'required' => false
                    ],[ 
                        'link_parameters'=>[
                        'context' => 'event'
                    ]])
                ->add('picturePosition', ChoiceType::class, ['choices' => $PicChoices])
                ->add('imgFilterColor', ColorType::class)
                ->add('imgFilterBlendMode', ChoiceType::class, [
                    'choices' => [
                            'luminosity' => 'mix-blend-mode: luminosity',
                            'multiply' => 'mix-blend-mode: multiply',
                            'exclusion' => 'mix-blend-mode: exclusion',
                        ]
                ]);
            if ($event->getexternalUrl()==false){
                $formMapper
                    ->add('headerTheme', null,[
                        'help' => 'possible values: light and dark'
                    ])
                    ->add('css');
            }
            $formMapper
                ->end()
                ->with('Links', ['class' => 'col-md-4'])
                    ->add('attachment', ModelListType::class,[
                        'required' => false,
                        'help' => 'added as downloadable link'
                        ],[ 
                            'link_parameters'=>[
                            'context' => 'event',
                            'provider' => 'sonata.media.provider.file'
                        ]])
                ->add('epics', null, ['help' => 'link to ePics pictures'])
                ->add('includeSaferSpaceGuidelines', null, ['help' => 'add it to the link list'])
                ->add('links', ImmutableArrayType::class, [
                    'help_html' => true,
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
                ->with('Config')
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
                ->end()
                ->tab('RSVP')
                ->with('Config')
                    ->add('rsvpSystemEnabled', null, ['help' => 'allow RSVP to the event'])
                    ->add('RSVPEmailSubject')
                    ->add('RSVPEmailBody', SimpleFormatterType::class, [
                        'format' => 'richhtml', 
                        'required' => false,
                        'ckeditor_context' => 'default', 
                    ])
                ->end()
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
        $collection->add('rsvpEmail', $this->getRouterIdParameter().'/send_rsvp_email');
        $collection->add('nakkiList', $this->getRouterIdParameter().'/nakkilist');
    }
}
