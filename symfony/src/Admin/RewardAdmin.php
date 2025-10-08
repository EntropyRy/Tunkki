<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Reward;
use Knp\Menu\ItemInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;

/**
 * @extends AbstractAdmin<Reward>
 */
final class RewardAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'reward';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('user')
            ->add('reward')
            ->add('paid')
            ->add('paidDate')
            ->add('updatedAt');
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('user')
            ->add('reward')
            ->add('Weight')
            ->add('Evenout')
            ->add('paid')
            ->add('paidDate')
            ->add('PaymentHandledBy')
            ->add('updatedAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'makepaid' => [
                        'template' => 'admin/crud/list__action_makepaid.html.twig',
                    ],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('bookings', null, ['disabled' => true])
            ->add('user', null, ['disabled' => true])
            ->add('reward', null, ['disabled' => true])
            ->add('paid')
            ->add('paidDate', DateTimePickerType::class, ['disabled' => true])
            ->add('PaymentHandledBy', null, ['disabled' => true])
            ->add('updatedAt', DateTimePickerType::class, ['disabled' => true]);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('user')
            ->add('bookings')
            ->add('reward')
            ->add('Evenout')
            ->add('Weight')
            ->add('paid')
            ->add('paidDate')
            ->add('paymentHandledBy')
            ->add('updatedAt');
    }

    #[\Override]
    public function preUpdate($reward): void
    {
    }

    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('makepaid', $this->getRouterIdParameter().'/makepaid');
        $collection->add('PrepareEvenout', 'evenout/prepare');
        $collection->add('Evenout', 'evenout/make');
    }

    #[\Override]
    public function configureTabMenu(ItemInterface $menu, $action, ?AdminInterface $childAdmin = null): void
    {
        $menu->addChild('Evenout', [
            'route' => 'admin_app_reward_PrepareEvenout',
        ])->setAttribute('icon', 'fa fa-balance-scale');
    }
}
