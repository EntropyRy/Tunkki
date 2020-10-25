<?php

namespace App\Form;

use App\Entity\EventArtistInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

class EventArtistInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Artist', null, [
                'choices' => $options['artists'],
                'choice_value' => 'name',
                'help' => 'new_artist_help_html',
                'help_html' => true
            ])
            ->add('WishForPlayTime')
            ->add('SetLength')
            //->add('StartTime', TimeType::class)
            //->add('Event', null, ['disabled' => true])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EventArtistInfo::class,
            'artists' => null
        ]);
    }
}
