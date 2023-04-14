<?php

declare(strict_types=1);

namespace App\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\Form\Type\DatePickerType;
use Sonata\Form\Type\ImmutableArrayType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\UrlsType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;

final class EventAdmin extends AbstractAdmin
{
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'event';
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        // display the first page (default = 1)
        $sortValues[DatagridInterface::PAGE] = 1;

        // reverse order (default = 'ASC')
        $sortValues[DatagridInterface::SORT_ORDER] = 'DESC';

        // name of the ordered field (default = the model's id field, if any)
        $sortValues[DatagridInterface::SORT_BY] = 'EventDate';
    }

    protected function configureTabMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null): void
    {
        if (!$childAdmin && !in_array($action, ['edit', 'show'])) {
            return;
        }
        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');
        $event = $admin->getSubject();
        if ($this->isGranted('EDIT')) {
            $menu->addChild(
                \Event::class,
                $admin->generateMenuUrl('edit', ['id' => $id])
            );
            $menu->addChild(
                'Artist editor',
                $admin->generateMenuUrl('admin.event_artist_info.list', ['id' => $id])
            );
            if ((is_countable($admin->getSubject()->getRSVPs()) ? count($admin->getSubject()->getRSVPs()) : 0) > 0) {
                $menu->addChild(
                    'RSVPs',
                    [
                        'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.rsvp.list', ['id' => $id])
                    ]
                );
                $menu->addChild(
                    'RSVP List',
                    [
                        'uri' => $admin->generateUrl('rsvp', ['id' => $id])
                    ]
                );
            }
            if ($event->getNakkikoneEnabled()) {
                $menu->addChild(
                    'Nakkikone',
                    [
                        'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.nakki.list', ['id' => $id])
                    ]
                );
                $menu->addChild(
                    'Nakit',
                    [
                        'uri' => $admin->generateUrl('entropy.admin.event|entropy.admin.nakki_booking.list', ['id' => $id])
                    ]
                );
                $menu->addChild(
                    'Printable Nakkilist',
                    [
                        'uri' => $admin->generateUrl('nakkiList', ['id' => $id])
                    ]
                );
            }
            if ($event->getTicketsEnabled()) {
                $menu->addChild(
                    'Tickets',
                    [
                        'uri' => $admin->generateUrl('admin.ticket.list', ['id' => $id])
                    ]
                );
                if ($event->getTicketCount() != (is_countable($event->getTickets()) ? count($event->getTickets()) : 0)) {
                    $menu->addChild(
                        'Update Ticket Count',
                        [
                            'uri' => $admin->generateUrl('admin.ticket.updateTicketCount', ['id' => $id]),
                            'attributes' => ['class' => 'btn-warning']
                        ]
                    );
                }
                if ($event->ticketPresaleEnabled()) {
                    $event = $this->getSubject();
                    $menu->addChild(
                        'Ticket Presale Preview',
                        [
                            'route' => 'entropy_event_ticket_presale',
                            'routeParameters' => [
                                'slug' => $event->getUrl(),
                                'year' => $event->getEventDate()->format('Y'),
                            ],
                            'linkAttributes' => ['target' => '_blank']
                        ]
                    );
                }
            }
            $menu->addChild(
                'Emails',
                [
                    'uri' => $admin->generateUrl('entropy.admin.event|admin.email.list', ['id' => $id])
                ]
            );
            $menu->addChild(
                'Notifications',
                [
                    'uri' => $admin->generateUrl('entropy.admin.event|admin.notification.list', ['id' => $id])
                ]
            );
            $menu->addChild(
                'Preview',
                [
                    'route' => 'entropy_event',
                    'routeParameters' => [
                        'id' => $id,
                    ],
                    'linkAttributes' => ['target' => '_blank']
                ]
            );
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
            ->add(
                'type',
                ChoiceFilter::class,
                [
                    'field_type' => ChoiceType::class,
                    'field_options' => ['choices' => $TypeChoices]
                ]
            )
            ->add('EventDate')
            ->add('publishDate')
            ->add('css')
            ->add('url')
            ->add('sticky');
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('EventDate')
            ->add('until')
            ->addIdentifier('Name', null, ['route' => ['name' => 'edit']])
            ->add('type')
            ->add('publishDate')
            ->add('url')
            ->add(
                ListMapper::NAME_ACTIONS,
                null,
                [
                    'actions' => [
                        'show' => [],
                        'edit' => [],
                    ],
                ]
            );
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
                ->add(
                    'EventDate',
                    DateTimePickerType::class,
                    [
                        'label' => 'Event Date and Time',
                        'format' => 'd.M.yyyy HH:mm',
                    ]
                )
                ->add(
                    'until',
                    DateTimePickerType::class,
                    [
                        'format' => 'd.M.yyyy HH:mm',
                        'label' => 'Event stop time',
                        'required' => false,
                    ]
                )
                ->add('published', null, ['help' => 'The addvert will be available when the publish date has been reached otherwise not'])
                ->add(
                    'publishDate',
                    DateTimePickerType::class,
                    [
                        'help' => 'Select date and time for this to be published if it is in the future you should have published on.',
                        'required' => false,
                        'format' => 'd.M.yyyy HH:mm',
                    ]
                )
                ->add(
                    'externalUrl',
                    null,
                    [
                        'label' => 'Is the advertisement hosted somewhere else? if this is selected and url is empty it can be used to have the event in events list.',
                        'help' => 'Is the add hosted here?'
                    ]
                )
                ->add(
                    'url',
                    null,
                    [
                        'help' => '\'event\' resolves to https://entropy.fi/(year)/event. In case of external need whole url like: https://entropy.fi/rave/bunka1'
                    ]
                )
                ->end();
        } else {
            $event = $this->getSubject();
            //if($event->getType() == 'announcement'){}
            $formMapper
                ->tab(\Event::class)
                ->with('English', ['class' => 'col-md-6'])
                ->add('Name');
            if ($event->getexternalUrl() == false) {
                $formMapper
                    ->add(
                        'Content',
                        SimpleFormatterType::class,
                        [
                            'format' => 'richhtml',
                            'required' => false,
                            'help' => 'use special tags {{ streamplayer }}, {{ timetable }}, {{ bios }}, {{ vj_bios }}, {{ rsvp }}, {{ links }}, {{ ticket }} as needed.'
                        ]
                    );
            }
            $formMapper
                ->end()
                ->with('Finnish', ['class' => 'col-md-6'])
                ->add('Nimi');
            if ($event->getexternalUrl() == false) {
                $formMapper
                    ->add(
                        'Sisallys',
                        SimpleFormatterType::class,
                        [
                            'format' => 'richhtml',
                            'required' => false,
                            'help' => 'käytä erikoista tagejä {{ streamplayer }}, {{ timetable }}, {{ bios }}, {{ vj_bios }}, {{ rsvp }}, {{ links }}, {{ ticket }} niinkun on tarve.'
                        ]
                    );
            }
            $formMapper
                ->end()
                ->with('Functionality', ['class' => 'col-md-4'])
                ->add('type', ChoiceType::class, ['choices' => $TypeChoices])
                ->add('cancelled', null, ['help' => 'Event has been cancelled'])
                ->add(
                    'EventDate',
                    DateTimePickerType::class,
                    [
                        'label' => 'Event Date and Time',
                        'format' => 'd.M.yyyy HH:mm',
                    ]
                )
                ->add(
                    'until',
                    DateTimePickerType::class,
                    [
                        'format' => 'd.M.yyyy HH:mm',
                        'label' => 'Event stop time',
                        'required' => false,
                    ]
                )
                ->add('published', null, ['help' => 'The addvert will be available when the publish date has been reached otherwise not'])
                ->add(
                    'publishDate',
                    DateTimePickerType::class,
                    [
                        'help' => 'Select date and time for this to be published if it is in the future you should have published on.',
                        'required' => false,
                        'format' => 'd.M.yyyy HH:mm',
                    ]
                )
                ->add(
                    'externalUrl',
                    null,
                    [
                        'label' => 'External Url/No addvert at all if url is empty',
                        'help' => 'Is the add hosted here?'
                    ]
                )
                ->add(
                    'url',
                    null,
                    [
                        'help' => '\'event\' resolves to https://entropy.fi/(year)/event.
                     In case of external need whole url like: https://entropy.fi/rave/bunka1'
                    ]
                )
                ->add(
                    'streamPlayerUrl',
                    null,
                    [
                        'help' => 'use {{ streamplayer }} in content. Applies the player in the advert when the event is happening.'
                    ]
                )
                ->end()
                ->with('Eye Candy', ['class' => 'col-md-4'])
                ->add(
                    'picture',
                    ModelListType::class,
                    [
                        'required' => false
                    ],
                    [
                        'link_parameters' => [
                            'context' => 'event'
                        ]
                    ]
                )
                ->add('picturePosition', ChoiceType::class, ['choices' => $PicChoices])
                ->add('imgFilterColor', ColorType::class)
                ->add(
                    'imgFilterBlendMode',
                    ChoiceType::class,
                    [
                        'required' => false,
                        'help' => 'Color does not work if you dont choose here how it should work',
                        'choices' => [
                            'luminosity' => 'mix-blend-mode: luminosity',
                            'multiply' => 'mix-blend-mode: multiply',
                            'exclusion' => 'mix-blend-mode: exclusion',
                            'difference' => 'mix-blend-mode: difference',
                            'screen' => 'mix-blend-mode: screen',
                        ]
                    ]
                );
            if ($event->getexternalUrl() == false) {
                $formMapper
                    ->add(
                        'headerTheme',
                        ChoiceType::class,
                        [
                            'required' => true,
                            'choices' => [
                                'light' => 'light',
                                'dark' => 'dark'
                            ]
                        ]
                    )
                    ->add(
                        'backgroundEffect',
                        ChoiceType::class,
                        [
                            'required' => false,
                            'choices' => [
                                'TV white noise' => 'tv',
                                'VHS static' => 'vhs',
                                'Snowfall' => 'snow',
                                'Liquid Lights' => 'liquid_lights',
                                'Color Grid' => 'grid',
                            ]
                        ]
                    )
                    ->add(
                        'backgroundEffectPosition',
                        ChoiceType::class,
                        [
                            'required' => true,
                            'choices' => [
                                'Background' => 'z-index:0;',
                                'In front' => 'z-index:1;',
                            ]
                        ]
                    )
                    ->add(
                        'backgroundEffectOpacity',
                        RangeType::class,
                        [
                            'required' => false,
                            'attr' => [
                                'min' => 0,
                                'max' => 100
                            ],
                            'help' => 'left transparent, right solid'
                        ]
                    )
                    ->add('css');
            }
            $formMapper
                ->end()
                ->with('Links', ['class' => 'col-md-4'])
                ->add(
                    'attachment',
                    ModelListType::class,
                    [
                        'required' => false,
                        'help' => 'added as downloadable link'
                    ],
                    [
                        'link_parameters' => [
                            'context' => 'event',
                            'provider' => 'sonata.media.provider.file'
                        ]
                    ]
                )
                ->add('epics', null, ['help' => 'link to ePics pictures'])
                ->add('includeSaferSpaceGuidelines', null, ['help' => 'add it to the link list'])
                ->add('webMeetingUrl', null, ['help' => 'Will be shown as a link 8 hours before and 2 hours after event start time'])
                ->add(
                    'links',
                    ImmutableArrayType::class,
                    [
                        'help_html' => true,
                        'help' => 'Titles are translated automatically. examples: tickets, fb.event, map.<br>
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
                        ]
                    ]
                )
                ->end()
                ->end()
                ->tab('Artist Sign up config')
                ->with('Config')
                ->add('artistSignUpEnabled', null, ['help' => 'Is the artist signup enabled'])
                ->add('artistSignUpAskSetLength', null, ['label' => 'Do we ask set length?'])
                ->add('showArtistSignUpOnlyForLoggedInMembers', null, ['help' => 'Do you have to be logged in to see artist sign up link for the event'])
                ->add(
                    'artistSignUpStart',
                    DateTimePickerType::class,
                    [
                        'format' => 'd.M.yyyy HH:mm',
                        'help' => 'when the artist signup starts',
                        'input' => 'datetime_immutable',
                        'required' => false
                    ]
                )
                ->add(
                    'artistSignUpEnd',
                    DateTimePickerType::class,
                    [
                        'format' => 'd.M.yyyy HH:mm',
                        'help' => 'when the artist signup ends',
                        'input' => 'datetime_immutable',
                        'required' => false
                    ]
                )
                ->end()
                ->end()
                ->tab('Nakkikone config')
                ->with('Config')
                ->add(
                    'NakkikoneEnabled',
                    null,
                    [
                        'help' => 'Publish nakkikone and allow members to reserve Nakkis',
                    ]
                )
                ->add(
                    'showNakkikoneLinkInEvent',
                    null,
                    [
                        'help' => 'Publish nakkikone in event',
                    ]
                )
                ->add(
                    'requireNakkiBookingsToBeDifferentTimes',
                    null,
                    [
                        'help' => 'Make sure member nakki bookings do not overlap',
                    ]
                )
                ->add(
                    'nakkiInfoEn',
                    SimpleFormatterType::class,
                    [
                        'format' => 'richhtml',
                        'required' => false,
                        'ckeditor_context' => 'default',
                    ]
                )
                ->add(
                    'nakkiInfoFi',
                    SimpleFormatterType::class,
                    [
                        'format' => 'richhtml',
                        'required' => false,
                        'ckeditor_context' => 'default',
                    ]
                )
                ->end()
                ->end()
                ->tab('RSVP')
                ->with('Config')
                ->add('rsvpSystemEnabled', null, ['help' => 'allow RSVP to the event'])
                ->end()
                ->end()
                ->tab('Tickets')
                ->with('Config', ['class' => 'col-md-6'])
                ->add('ticketsEnabled', null, ['help' => 'allow tikets to the event'])
                ->add('nakkiRequiredForTicketReservation', null, ['help' => 'allow tikets to be reserved only after nakki reservation'])
                ->add('ticketCount', null, ['help' => 'How many tickets there are? When event is updated and ticket update button is pushed this amount will be created with the price below. 2 prices for tickets? first create low price tickets that are in presales and then the amount of full price tickets'])
                ->add('ticketPrice', null, ['help' => 'What is price for a one ticket'])
                ->end()
                ->with('Presales', ['class' => 'col-md-6'])
                ->add(
                    'ticketPresaleStart',
                    DatePickerType::class,
                    [
                        'format' => 'd.M.yyyy',
                        'help' => 'When presale starts',
                        'input' => 'datetime_immutable',
                        'required' => false
                    ]
                )
                ->add(
                    'ticketPresaleEnd',
                    DateTimePickerType::class,
                    [
                        'format' => 'd.M.yyyy, HH:mm',
                        'help' => 'when presale ends. If start of the ticket sale needs to be timed: define start and end seconds apart',
                        'input' => 'datetime_immutable',
                        'required' => false
                    ]
                )
                ->add(
                    'ticketPresaleCount',
                    null,
                    [
                        'help' => 'How many tickets can be sold in presale?'
                    ]
                )
                ->end()
                ->with('Info', ['class' => 'col-md-12'])
                ->add(
                    'ticketInfoFi',
                    SimpleFormatterType::class,
                    [
                        'format' => 'richhtml',
                        'required' => false,
                        'ckeditor_context' => 'default',
                    ]
                )
                ->add(
                    'ticketInfoEn',
                    SimpleFormatterType::class,
                    [
                        'format' => 'richhtml',
                        'required' => false,
                        'ckeditor_context' => 'default',
                    ]
                )
                ->end()
                ->end();
        }
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('Name')
            ->add('Nimi')
            ->add('EventDate')
            ->add('publishDate')
            ->add('css')
            ->add('Content')
            ->add('Sisallys')
            ->add('updatedAt');
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('show');
        $collection->remove('delete');
        $collection->add('rsvp', $this->getRouterIdParameter() . '/rsvp');
        $collection->add('nakkiList', $this->getRouterIdParameter() . '/nakkilist');
    }
    public function prePersist($event): void
    {
        if ($event->getType() == 'clubroom') {
            $event->setLinks(
                [
                    'urls' => [0 => [
                        'url' => 'https://reittiopas.hsl.fi/reitti/-/J%C3%A4mer%C3%A4ntaival%203%20A%2C%20Espoo%3A%3A60.18730249466484%2C24.836112856864933',
                        'icon' => 'fas fa-map',
                        'title' => 'map',
                        'open_in_new_window' => true,
                    ]]
                ]
            );
        }
    }
}
