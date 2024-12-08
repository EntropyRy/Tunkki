<?php

declare(strict_types=1);

namespace App\Admin;

use Knp\Menu\ItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

/**
 * @extends AbstractAdmin<object>
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
                'accessor' => fn($subject) => $subject->getAmount() / 100 . '€'
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
                'disabled' => true
            ])
            ->add('nameFi')
            ->add(
                'picture',
                ModelListType::class,
                [
                    'required' => false
                ],
                [
                    'link_parameters' => [
                        'context' => 'product'
                    ]
                ]
            )
            ->add('descriptionFi')
            ->add('descriptionEn')
            ->add('serviceFee', null, ['help' => 'One product will be forced to all transactions'])
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
        $collection->add('fetch_from_stripe', 'fetch-from-stripe');
    }
    #[\Override]
    public function configureTabMenu(ItemInterface $menu, $action, ?AdminInterface $childAdmin = null): void
    {
        $menu->addChild('Fetch from Stripe', [
            'route' => 'admin_app_product_fetch_from_stripe',
        ])->setAttribute('icon', 'fa fa-download');
    }
}
