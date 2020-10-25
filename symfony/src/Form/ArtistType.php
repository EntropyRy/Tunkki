<?php

namespace App\Form;

use App\Entity\Artist;
use App\Form\MemberType;
use App\Form\UrlsType;
use App\Form\EventArtistInfoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
//use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\MediaBundle\Form\Type\MediaType;

class ArtistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, ['label' => 'artist_name'])
            ->add('type', ChoiceType::class, [
                'label' => 'artist_type',
                'choices' => [
                    'DJ' => 'DJ',
                    'Live' => 'Live',
                    'VJ' => 'VJ'
                ]
            ])
            ->add('genre', null, ['label' => 'artist_genre','help' => 'genre_help'])
            ->add('bio', null, ['label' => 'artist_bio','help' => 'bio_help'])
            ->add('bioEn', null, ['label' => 'artist_bio_en','help' => 'bio_help'])
            ->add('hardware', null, ['label' => 'artist_hardware','help' => 'hardware_help'])
            ->add('links', CollectionType::class, [
                'label' => 'artist_links',
                'entry_type' => UrlsType::class,
                'allow_add' => true,
                'by_reference' => false,
                'allow_delete' => true
            ])
            ->add('picture', MediaType::class, [
                    'label' => 'artist_promo_picture',
                    'provider' => 'sonata.media.provider.image',
                    'context' => 'artist',
                    'new_on_update' => true,
                    'translation_domain' => 'messages',
                ])
/*            ->add('eventArtistInfos', CollectionType::class, [
                'entry_type' => EventArtistInfoType::class,
                'allow_add' => false,
])*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Artist::class,
        ]);
    }
}
