<?php

namespace App\Form;

use App\Entity\Artist;
use App\Entity\StreamArtist;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StreamArtistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $member = $options['member'];
        if (!$member) {
            return;
        }
        $choices = $member->getStreamArtists();
        $stream = $options['stream'];
        $isInStream = $options['is_in_stream'] ?? false;

        if ($isInStream) {
            // If artist is already in stream, just add a hidden field for form submission
            $builder->add('isRemoving', HiddenType::class, [
                'mapped' => false,
                'data' => true,
            ]);
        } else {
            // Otherwise, show artist selector - only showing the member's artists
            $builder
                ->add('artist', EntityType::class, [
                    'class' => Artist::class,
                    'required' => true,
                    'choices' => $choices,
                    'choice_label' => 'name',
                    'label' => 'stream.artist.label',
                    'placeholder' => 'stream.artist.select',
                    'constraints' => [
                        new \Symfony\Component\Validator\Constraints\NotNull([
                            'message' => 'stream.artist.notnull',
                        ]),
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'member' => null,
            'stream' => null,
            'is_in_stream' => false,
            'data_class' => StreamArtist::class,
        ]);
    }
}
