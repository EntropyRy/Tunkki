<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class HappeningAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('nameFi')
            ->add('nameEn')
            ->add('descriptionFi')
            ->add('descriptionEn')
            ->add('time')
            ->add('needsPreliminarySignUp')
            ->add('needsPreliminaryPayment')
            ->add('paymentInfoFi')
            ->add('paymentInfoEn')
            ->add('type')
            ->add('maxSignUps')
            ->add('slugFi')
            ->add('slugEn')
            ->add('priceFi')
            ->add('priceEn')
            ->add('owners')
            ->add('releaseThisHappeningInEvent');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('nameFi')
            ->add('nameEn')
            ->add('time')
            ->add('type')
            ->add('releaseThisHappeningInEvent')
            ->add('owners')
            ->add('bookings')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'preview' => ['template' => 'admin/crud/list__action_happening_preview.html.twig'],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Restaurant' => 'restaurant',
                    'Event' => 'event'
                ]
            ])
            ->add('nameFi')
            ->add('slugFi')
            ->add('descriptionFi')
            ->add('paymentInfoFi')
            ->add('priceFi')
            ->add('nameEn')
            ->add('slugEn')
            ->add('descriptionEn')
            ->add('paymentInfoEn')
            ->add('priceEn')
            ->add('time')
            ->add('needsPreliminarySignUp')
            ->add('needsPreliminaryPayment')
            ->add('maxSignUps')
            ->add('releaseThisHappeningInEvent')
            ->add('owners')
            ->add('bookings');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('nameFi')
            ->add('nameEn')
            ->add('descriptionFi')
            ->add('descriptionEn')
            ->add('time')
            ->add('needsPreliminarySignUp')
            ->add('needsPreliminaryPayment')
            ->add('paymentInfoFi')
            ->add('paymentInfoEn')
            ->add('type')
            ->add('maxSignUps')
            ->add('slugFi')
            ->add('slugEn')
            ->add('priceFi')
            ->add('priceEn')
            ->add('releaseThisHappeningInEvent')
            ->add('owners')
            ->add('bookings');
    }
}
