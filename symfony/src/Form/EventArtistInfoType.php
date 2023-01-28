<?php

namespace App\Form;

use App\Entity\EventArtistInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Artist;

class EventArtistInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Artist', null, [
                'choices' => $options['artists'],
                'choice_label' => fn (Artist $artist) => $artist->getGenre() ? $artist->getName() . ' (' . $artist->getGenre() . ')' : $artist->getName(),
                'required' => true,
                'label' => 'event.form.sign_up.artist',
                'help' => 'event.form.sign_up.new_artist_help_html',
                'help_html' => true,
            ])
            ->add('WishForPlayTime', null, [
                'label' => 'event.form.sign_up.wish_for_playtime'
            ]);
        if($options['ask_time']){
        $builder
            ->add('SetLength', null, [
                'label' => 'event.form.sign_up.set_length'
            ]);
        }
        $builder
            ->add('freeWord', null, [
                'label' => 'event.form.sign_up.free_word',
                'help' => 'event.form.sign_up.why_should_we_choose_you'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventArtistInfo::class,
            'artists' => null,
            'ask_time' => true
        ]);
    }
}
