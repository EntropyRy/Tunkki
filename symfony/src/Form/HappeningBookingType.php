<?php

namespace App\Form;

use App\Entity\HappeningBooking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HappeningBookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('comment', null, [
                'row_attr' => ['class' => $options['comments'] ? '' : 'd-none']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'comments' => true,
            'data_class' => HappeningBooking::class,
        ]);
    }
}
