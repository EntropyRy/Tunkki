<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<object>
 */
final class CheckoutAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('cart.email')
            ->add('stripeSessionId')
            ->add('status')
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('cart.email')
            ->add('cart.products')
            ->add('status')
            ->add('createdAt')
            ->add('updatedAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('status');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('stripeSessionId')
            ->add('status')
            ->add('createdAt')
            ->add('updatedAt');
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('delete');
        $collection->remove('create');
        $collection->add('remove_unneeded', 'remove-unneeded');
    }
    public function configureTabMenu(\Knp\Menu\ItemInterface $menu, $action, \Sonata\AdminBundle\Admin\AdminInterface $childAdmin = null): void
    {
        $menu->addChild('Remove Unneeded', [
            'route' => 'admin_app_checkout_remove_unneeded',
        ])->setAttribute('icon', 'fa fa-remove');
    }
}
