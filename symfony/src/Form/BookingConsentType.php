<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class BookingConsentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($builder->getData()->getRenterConsent()){
            $class = 'disabled';
            $disabled = true;
        } else {
            $class = 'btn-large btn-primary btn';
            $disabled = false;
        }
        $builder
            ->add('renterConsent')
            ->add('Agree', SubmitType::class, [
                'disabled' => $disabled,
                'attr' => ['class' => $class]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
