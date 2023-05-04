<?php

namespace App\Form;

use App\Entity\Artist;
use App\Form\UrlsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
//use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\MediaBundle\Form\Type\MediaType;

class ArtistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => 'artist.form.name'])
            ->add('type', ChoiceType::class, [
                'label' => 'artist.form.type',
                'choices' => [
                    'DJ' => 'DJ',
                    'Live' => 'Live',
                    'VJ' => 'VJ',
                    'ART' => 'ART'
                ]
            ])
            ->add('hardware', null, [
                'label' => 'artist.form.hardware',
                'help' => 'artist.form.hardware_help',
                'required' => true
            ])
            ->add('genre', null, ['label' => 'artist.form.genre', 'help' => 'artist.form.genre_help'])
            ->add('bio', null, ['label' => 'artist.form.bio', 'help' => 'artist.form.bio_help'])
            ->add('bioEn', null, ['label' => 'artist.form.bio_en', 'help' => 'artist.form.bio_help'])
            ->add(
                'links',
                CollectionType::class,
                [
                    'label' => 'artist.form.links',
                    'allow_add' => true,
                    'by_reference' => false,
                    'allow_delete' => true,
                    'delete_empty' => true,
                    'prototype' => true,
                    'entry_type' => UrlsType::class,
                    'attr' => ['class' => 'row'],
                    'entry_options' => [
                        'row_attr' => ['class' => 'col-md-6 col-12'],
                        'label' => false
                    ],
                ],
            )
            ->add('Picture', MediaType::class, [
                'label' => 'artist.form.promo_picture',
                'provider' => 'sonata.media.provider.image',
                'context' => 'artist',
                'translation_domain' => 'messages',
            ])
            /*            ->add('eventArtistInfos', CollectionType::class, [
                'entry_type' => EventArtistInfoType::class,
                'allow_add' => false,
])*/;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Artist::class,
        ]);
    }
}
