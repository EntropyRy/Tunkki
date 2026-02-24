<?php

declare(strict_types=1);

namespace App\Form\Rental\Booking;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<array<string, mixed>> */
class RentalRequestType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('eventName', TextType::class, [
                'required' => false,
                'label' => 'rental_request.event_name',
            ])
            ->add('bookingDate', DateType::class, [
                'required' => true,
                'widget' => 'single_text',
                'label' => 'rental_request.booking_date',
                'label_attr' => ['class' => 'required'],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('renterName', TextType::class, [
                'required' => true,
                'label' => 'rental_request.renter_name',
                'label_attr' => ['class' => 'required'],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'rental_request.email',
                'label_attr' => ['class' => 'required'],
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->add('phone', TelType::class, [
                'required' => true,
                'label' => 'rental_request.phone',
                'label_attr' => ['class' => 'required'],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('organization', TextType::class, [
                'required' => false,
                'label' => 'rental_request.organization',
            ])
            ->add('streetadress', TextType::class, [
                'required' => false,
                'label' => 'rental_request.streetadress',
            ])
            ->add('zipcode', TextType::class, [
                'required' => false,
                'label' => 'rental_request.zipcode',
            ])
            ->add('city', TextType::class, [
                'required' => false,
                'label' => 'rental_request.city',
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'label' => 'rental_request.message',
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
