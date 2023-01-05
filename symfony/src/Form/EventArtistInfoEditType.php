<?php

namespace App\Form;

use App\Entity\EventArtistInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventArtistInfoEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Artist', null, [
                'disabled' => true,
                'required' => true,
                'label' => 'event.form.sign_up.artist',
            ])
            ->add('WishForPlayTime', null, [
                'label' => 'event.form.sign_up.wish_for_playtime'
            ])
            ->add('SetLength', null, [
                'label' => 'event.form.sign_up.set_length'
            ])
            ->add('freeWord', null, [
                'label' => 'event.form.sign_up.free_word',
                'help' => 'event.form.sign_up.why_should_we_choose_you'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventArtistInfo::class,
            'artists' => null
        ]);
    }
}
