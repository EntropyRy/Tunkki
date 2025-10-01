<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarConfigType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('add_events', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'calendar.add_events',
            ])
            ->add('add_notifications_for_events', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'calendar.add_notifications_for_events',
            ])
            ->add('add_clubroom_events', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'calendar.add_clubroom_events',
            ])
            ->add('add_notifications_for_clubroom_events', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'calendar.add_notifications_for_clubroom_events',
            ])
            ->add('add_meetings', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'calendar.add_meetings',
            ])
            ->add('add_notifications_for_meetings', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'calendar.add_notifications_for_meetings',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
