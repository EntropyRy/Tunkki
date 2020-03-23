<?php

declare(strict_types=1);

namespace App\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
//use Sonata\MediaBundle\Form\Type\MediaType;

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
            $menu->addChild('Preview', [
                'route' => 'entropy_event',
                'routeParameters' => [
                    'id'=> $id,
                    '_locale' => $this->getRequest()->getLocale()
                 ],
                 'linkAttributes' => ['target' => '_blank']
            ]);
       }
    }
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('Name')
            ->add('Content')
            ->add('Nimi')
            ->add('Sisallys')
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
        $PlaceChoices = [
            'All' => 'all',
        ];
        $PicChoices = [
            'Banner' => 'banner',
            'Right side of the text' => 'right',
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
                ->add('externalUrl', null, ['help'=>'Is the add hosted here?'])
                ->add('url', null, [
                    'help' => '\'event\' resolves to https://entropy.fi/(year)/event. In case of external need whole url like: https://entropy.fi/rave/bunka1'])
                ->end();
        } else {
            $event = $this->getSubject();
            //if($event->getType() == 'announcement'){}
            $formMapper
                ->with('English', ['class' => 'col-md-6'])
                ->add('Name');
            if ($event->getexternalUrl()==false){
                $formMapper
                    ->add('Content', SimpleFormatterType::class, [
                        'format' => 'richhtml', 
                        'required' => false,
                        'ckeditor_context' => 'default',
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
                        'ckeditor_context' => 'default' 
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
                ->add('sticky', null, ['help' => 'Shown first on frontpage. There can only be one!'])
                ->add('publishDate', DateTimePickerType::class, [
                    'help' => 'If this needs to be released at certain time',
                    'required' => false
                    ]
                )
                ->add('publishPlaces', ChoiceType::class, ['choices' => $PlaceChoices])
                ->add('externalUrl', null, [
                    'label' => 'External Url/No add at all if url is empty',
                    'help'=>'Is the add hosted here?'
                ])
                ->add('url', null, [
                    'help' => '\'event\' resolves to https://entropy.fi/(year)/event. In case of external need whole url like: https://entropy.fi/rave/bunka1'])
                ->end()
                ->with('Eye Candy', ['class' => 'col-md-6'])
                ->add('picture', ModelListType::class,[
                        'required' => false
                    ],[ 
                        'link_parameters'=>[
                        'context' => 'event'
                    ]])
                ->add('picturePosition', ChoiceType::class, ['choices' => $PicChoices]);
            if ($event->getexternalUrl()==false){
                $formMapper
                    ->add('css');
            }
            $formMapper
                ->add('epics', null, ['help' => 'link to ePics pictures'])
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
}
