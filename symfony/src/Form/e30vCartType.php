<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;

class e30vCartType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disabled = false;
        if ($options['data']['email']) {
            $disabled = true;
        }
        $builder
            ->add('email', EmailType::class, [
                'disabled' => $disabled,
                'data' => $options['data']['email'],
                'help' => 'e30v.cart.email.help',
                'help_html' => true
            ])
            ->add('quantity', IntegerType::class, [
                'constraints' => [new Positive()],
                'attr' => [
                    'min' => 1
                ]
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'by_reference' => false,
            'prototype' => true,
        ]);
    }
}
