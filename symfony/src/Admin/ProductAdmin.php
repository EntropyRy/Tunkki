<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Product;
use Knp\Menu\ItemInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<Product>
 */
final class ProductAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'product';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('event');
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('nameEn')
            ->add('descriptionEn')
            ->add('active')
            ->add('event')
            ->add('amount', null, [
                'accessor' => fn ($subject): string => $subject->getAmount() / 100 .'€',
            ])
            ->add('ticket')
            ->add('quantity')
            ->add('sold')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('nameEn', null, [
                'help' => 'Defined in Stripe',
                'disabled' => true,
            ])
            ->add('nameFi')
            ->add(
                'picture',
                ModelListType::class,
                [
                    'required' => false,
                ],
                [
                    'link_parameters' => [
                        'context' => 'product',
                    ],
                ]
            )
            ->add('descriptionFi')
            ->add('descriptionEn')
            ->add('serviceFee', null, [
                'help' => 'One product will be forced to all transactions',
                'required' => false,
            ])
            ->add('ticket')
            ->add('quantity')
            ->add('howManyOneCanBuyAtOneTime')
            ->add('event');
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('stripeId')
            ->add('stripePriceId');
    }

    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
        $collection->remove('show');
    }

    #[\Override]
    public function configureTabMenu(ItemInterface $menu, $action, ?AdminInterface $childAdmin = null): void
    {
    }
}
