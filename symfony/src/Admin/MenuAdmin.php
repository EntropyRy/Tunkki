<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

final class MenuAdmin extends AbstractAdmin
{
    protected array $accessMapping = [
        'tree' => 'LIST',
    ];
    public function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('tree', 'tree');
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('label')
            ->add('nimi')
            ->add('url')
            ->add('enabled')
            ->add(ListMapper::NAME_ACTIONS, null, [
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
            ->add('label')
            ->add('pageEn')
            ->add('nimi')
            ->add('pageFi')
            ->add('url', null, ['help' => 'This does not work if Pages are selected'])
            ->add('parent')
            ->add('position')
            ->add('enabled')
        ;
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('label')
            ->add('nimi')
            ->add('url')
            ->add('enabled')
        ;
    }
}
