<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Artist;
use App\Entity\StreamArtist;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @extends AbstractType<StreamArtist>
 */
class StreamArtistType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $member = $options['member'];
        if (!$member) {
            return;
        }
        $choices = $member->getStreamArtists();
        if (!$choices) {
            return;
        }
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
                        new NotNull(message: 'stream.artist.notnull'),
                    ],
                ]);
        }
    }

    #[\Override]
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
