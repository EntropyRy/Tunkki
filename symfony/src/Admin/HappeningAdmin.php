<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\Form\Type\CollectionType;

final class HappeningAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add("nameFi")
            ->add("nameEn")
            ->add("descriptionFi")
            ->add("descriptionEn")
            ->add("time")
            ->add("needsPreliminarySignUp")
            ->add("needsPreliminaryPayment")
            ->add("paymentInfoFi")
            ->add("paymentInfoEn")
            ->add("type")
            ->add("maxSignUps")
            ->add("slugFi")
            ->add("slugEn")
            ->add("priceFi")
            ->add("priceEn")
            ->add("owners")
            ->add("releaseThisHappeningInEvent");
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add("nameFi")
            ->add("nameEn")
            ->add("time")
            ->add("type")
            ->add("releaseThisHappeningInEvent")
            ->add("owners")
            ->add("bookings")
            ->add(ListMapper::NAME_ACTIONS, null, [
                "actions" => [
                    "preview" => [
                        "template" =>
                            "admin/crud/list__action_happening_preview.html.twig",
                    ],
                    "edit" => [],
                    "delete" => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add(
                "picture",
                ModelListType::class,
                [
                    "required" => false,
                ],
                [
                    "link_parameters" => [
                        "context" => "artist",
                    ],
                ],
            )
            ->add("type", ChoiceType::class, [
                "choices" => [
                    "Restaurant" => "restaurant",
                    "Event" => "event",
                ],
            ])
            ->add("nameFi")
            ->add("slugFi")
            ->add("descriptionFi")
            ->add("paymentInfoFi")
            ->add("priceFi")
            ->add("nameEn")
            ->add("slugEn")
            ->add("descriptionEn")
            ->add("paymentInfoEn")
            ->add("priceEn")
            ->add("time")
            ->add("needsPreliminarySignUp")
            ->add("needsPreliminaryPayment")
            ->add("maxSignUps")
            ->add("releaseThisHappeningInEvent")
            ->add("owners")
            ->add(
                "bookings",
                CollectionType::class,
                [
                    "by_reference" => false,
                    "required" => false,
                ],
                [
                    "edit" => "inline",
                    "inline" => "table",
                ],
            );
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add("id")
            ->add("nameFi")
            ->add("nameEn")
            ->add("descriptionFi")
            ->add("descriptionEn")
            ->add("time")
            ->add("needsPreliminarySignUp")
            ->add("needsPreliminaryPayment")
            ->add("paymentInfoFi")
            ->add("paymentInfoEn")
            ->add("type")
            ->add("maxSignUps")
            ->add("slugFi")
            ->add("slugEn")
            ->add("priceFi")
            ->add("priceEn")
            ->add("releaseThisHappeningInEvent")
            ->add("owners")
            ->add("bookings");
    }
}
