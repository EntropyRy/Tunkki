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
            ->add('publishPlaces')
            ->add('css')
            ->add('url')
            ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('Name')
            ->add('Content')
            ->add('Nimi')
            ->add('Sisallys')
            ->add('EventDate')
            ->add('publishDate')
            ->add('publishPlaces')
            ->add('css')
            ->add('url')
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
            'Event' => 'Event',
            'Clubroom Event' => 'Kerde Event',
            'Announcement' => 'Announcement',
        ];
        $PlaceChoices = [
            'Wall' => 'Wall',
            'Own Page' => 'Own Page'
        ];


        $formMapper
            ->with('English', ['class' => 'col-md-6'])
            ->add('Name')
            ->add('Content', SimpleFormatterType::class, ['format' => 'richhtml'])
            ->end()
            ->with('Finnish', ['class' => 'col-md-6'])
            ->add('Nimi')
            ->add('Sisallys', SimpleFormatterType::class, ['format' => 'richhtml'])
            ->end()
            ->with('Functionality', ['class' => 'col-md-4'])
            ->add('type', ChoiceType::class, ['choices' => $TypeChoices])
            ->add('EventDate', DateTimePickerType::class, ['label' => 'Event Date and Time'])
            ->add('published', null, ['help' => 'Only logged in users can see if not published'])
            ->add('publishDate', DateTimePickerType::class, [
                'help' => 'If this needs to be released at certain time',
                'required' => false
                ]
            )
            ->add('publishPlaces')
            ->add('url')
            ->end()
            ->with('Eye Candy', ['class' => 'col-md-6'])
            ->add('picture', ModelListType::class,[],[ 
                'link_parameters'=>[
                'context' => 'event',
                'provider' => 'sonata.media.provider.image',
            ]])
            ->add('css')
            ->end()
            ;
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
