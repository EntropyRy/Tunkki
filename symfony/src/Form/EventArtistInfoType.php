<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<EventArtistInfo>
 */
class EventArtistInfoType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Artist', null, [
                'choices' => $options['artists'],
                'choice_label' => fn (Artist $artist): string => $artist->getGenre() ? $artist->getName().' ('.$artist->getGenre().')' : $artist->getName(),
                'required' => true,
                'label' => 'event.form.sign_up.artist',
                'help' => 'event.form.sign_up.new_artist_help_html',
                'help_html' => true,
                'disabled' => $options['disable_artist'] ?? false,
            ])
            ->add('WishForPlayTime', null, [
                'label' => 'event.form.sign_up.wish_for_playtime',
            ]);
        if ($options['ask_time']) {
            $builder
                ->add('SetLength', null, [
                    'label' => 'event.form.sign_up.set_length',
                ]);
        }
        $builder
            ->add('freeWord', null, [
                'label' => 'event.form.sign_up.free_word',
                'help' => 'event.form.sign_up.why_should_we_choose_you',
                'required' => true,
            ])
            ->add('agreeOnRecording', null, [
                'label' => 'event.form.sign_up.agree_on_recording',
                'help' => 'event.form.sign_up.agree_on_recording_help',
                'required' => true,
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventArtistInfo::class,
            'artists' => null,
            'ask_time' => true,
            'disable_artist' => false,
        ]);
    }
}
