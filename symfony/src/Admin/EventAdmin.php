<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;

final class EventAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'event';
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
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

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('Name')
            ->add('Nimi')
            ->add('EventDate')
            ->add('publishDate')
            ->add('publishPlaces')
            ->add('css')
            ->add('Content')
            ->add('Sisallys')
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
        $formMapper
            ->with('English', ['class' => 'col-md-8'])
            ->add('Name')
            ->add('Content')
            ->end()
            ->with('Finnish', ['class' => 'col-md-8'])
            ->add('Nimi')
            ->add('Sisallys')
            ->end()
            ->with('Functionality', ['class' => 'col-md-4'])
            ->add('EventDate', DateTimePickerType::class)
            ->add('publishDate', DateTimePickerType::class)
            ->add('publishPlaces')
            ->add('css')
            ->add('url')
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
