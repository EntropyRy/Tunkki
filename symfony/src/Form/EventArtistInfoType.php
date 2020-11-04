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
                'help' => 'event.form.sign_up.new_artist_help_html',
                'help_html' => true,
            ])
            ->add('WishForPlayTime',null, [
                'label' => 'event.form.sign_up.wish_for_playtime'
            ])
            ->add('SetLength', null, [
                'label' => 'event.form.sign_up.set_length'
            ])
            ->add('freeWord',null,[
                'label' => 'event.form.sign_up.free_word',
                'help'=> 'event.form.sign_up.why_should_we_choose_you'
            ])
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
