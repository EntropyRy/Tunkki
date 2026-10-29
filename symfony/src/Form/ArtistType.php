<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Artist;
use Arkounay\Bundle\UxCollectionBundle\Form\UxCollectionType;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Artist>
 */
class ArtistType extends AbstractType
{
    private const MAX_PICTURE_SIZE = 8 * 1024 * 1024;

    private const ALLOWED_PICTURE_MIME_TYPES = [
        'image/pjpeg',
        'image/jpeg',
        'image/png',
        'image/x-png',
        'image/gif',
        'image/webp',
    ];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[\Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('name', null, ['label' => 'artist.form.name'])
            ->add('type', ChoiceType::class, [
                'label' => 'artist.form.type',
                'attr' => ['class' => 'form-check-inline'],
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'DJ' => 'DJ',
                    'Live' => 'Live',
                    'VJ' => 'VJ',
                    'ART' => 'ART',
                ],
            ])
            ->add('hardware', null, [
                'label' => 'artist.form.hardware',
                'help' => 'artist.form.hardware_help',
                'required' => true,
            ])
            ->add('genre', null, [
                'label' => 'artist.form.genre',
                'help' => 'artist.form.genre_help',
            ])
            ->add('bio', null, [
                'label' => 'artist.form.bio',
                'help' => 'artist.form.bio_help',
            ])
            ->add('bioEn', null, [
                'label' => 'artist.form.bio_en',
                'help' => 'artist.form.bio_help',
            ])
            ->add('links', UxCollectionType::class, [
                'label' => 'artist.form.links',
                'allow_add' => true,
                'by_reference' => false,
                'allow_delete' => true,
                'delete_empty' => true,
                'allow_drag_and_drop' => true,
                'add_label' => 'Lisää/Add',
                'drag_and_drop_filter' => 'input,textarea,a,button,label',
                'add_class' => 'btn btn-secondary collection-add mt-2',
                'prototype' => true,
                'entry_type' => UrlWithIconType::class,
                'attr' => ['class' => 'row'],
                'min' => 0,
                'entry_options' => [
                    'row_attr' => ['class' => 'col-md-6 col-12'],
                    'label' => false,
                ],
            ])
            ->add(
                'Picture',
                MediaType::class,
                [
                    'label' => 'artist.form.promo_picture',
                    'provider' => 'sonata.media.provider.image',
                    'context' => 'artist',
                    'translation_domain' => 'messages',
                    'new_on_update' => true,
                    // Show the "required" error inline next to the widget instead
                    // of bubbling it up to a generic top-of-form message.
                    'error_bubbling' => false,
                ]
                /*            ->add('eventArtistInfos', CollectionType::class, [
                'entry_type' => EventArtistInfoType::class,
                'allow_add' => false,
])*/
            );

        // Sonata transforms its media form before nested file constraints run.
        // Check the uploaded file first so invalid public profile images have a
        // useful, localized error instead of exposing provider implementation
        // details.
        $pictureBuilder = $builder->get('Picture');
        $pictureBuilder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event): void {
                $data = $event->getData();
                $upload = \is_array($data) ? ($data['binaryContent'] ?? null) : null;

                if (!$upload instanceof UploadedFile) {
                    return;
                }

                if ($upload->getSize() > self::MAX_PICTURE_SIZE) {
                    $message = 'artist.form.picture_too_large';
                } elseif (!\in_array($upload->getMimeType(), self::ALLOWED_PICTURE_MIME_TYPES, true)) {
                    $message = 'artist.form.picture_invalid';
                } else {
                    return;
                }

                $event->getForm()->addError(new FormError($this->translator->trans($message)));
            },
        );
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Artist::class,
            'post_max_size_message' => $this->translator->trans('artist.form.picture_request_too_large'),
        ]);
    }
}
