<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class BookingConsentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('renterSignature', HiddenType::class)
            ->add('renterConsent');
        if ($builder->getData()->getRenterConsent()) {
            $builder
                ->add('Signed', SubmitType::class, [
                    'disabled' => true,
                    'attr' => ['class' => 'btn-secondary disabled']
                ]);
        } else {
            $builder
                ->add('Agree', SubmitType::class, [
                    'disabled' => true,
                    'attr' => [
                        'class' => 'btn-large btn-primary',
                        'data-turbo' => "false"
                    ]
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
