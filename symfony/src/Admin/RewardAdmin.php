<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\Form\Type\DateTimePickerType;

final class RewardAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'reward';
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('user')
            ->add('reward')
            ->add('paid')
            ->add('paidDate')
            ->add('updatedAt')
            ;
    }

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
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'makepaid' => [
                        'template' => 'admin/crud/list__action_makepaid.html.twig'
                    ],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('bookings', null , ['disabled' => true])
            ->add('user', null , ['disabled' => true])
            ->add('reward', null , ['disabled' => true])
            ->add('paid')
            ->add('paidDate', DateTimePickerType::class, ['disabled' => true])
            ->add('PaymentHandledBy', null , ['disabled' => true])
            ->add('updatedAt', DateTimePickerType::class, ['disabled' => true])
            ;
    }

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
            ->add('updatedAt')
            ;
    }
    public function preUpdate($reward)
    {
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('makepaid', $this->getRouterIdParameter().'/makepaid');
        $collection->add('PrepareEvenout', 'evenout/prepare');
        $collection->add('Evenout', 'evenout/make');
    }
    public function configureSideMenu(\Knp\Menu\ItemInterface $menu, $action, \Sonata\AdminBundle\Admin\AdminInterface $childAdmin = null)
    {
        $menu->addChild($this->trans('evenout'),[
            'route'=> 'admin_app_reward_PrepareEvenout',
        ])->setAttribute('icon', 'fa fa-balance-scale');
    }
    
}
