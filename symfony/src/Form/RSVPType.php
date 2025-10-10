<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RSVP;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RSVP>
 */
class RSVPType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', null, [
                'required' => true,
            ])
            ->add('lastName', null, [
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'required' => true,
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RSVP::class,
        ]);
    }
}
