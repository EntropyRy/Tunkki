<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

final class UrlWithIconType extends AbstractType
{
    #[\Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        // Define commonly used icon aliases with display labels (alphabetically ordered)
        $iconChoices = [
            'icons.brand_icons_label' => [
                'icons.brand_icons.bandcamp' => 'bandcamp',
                'icons.brand_icons.behance' => 'behance',
                'icons.brand_icons.discord' => 'discord',
                'icons.brand_icons.facebook' => 'facebook',
                'icons.brand_icons.github' => 'github',
                'icons.brand_icons.instagram' => 'instagram',
                'icons.brand_icons.linkedin' => 'linkedin',
                'icons.brand_icons.linktree' => 'linktree',
                'icons.brand_icons.mastodon' => 'mastodon',
                'icons.brand_icons.mixcloud' => 'mixcloud',
                'icons.brand_icons.pinterest' => 'pinterest',
                'icons.brand_icons.reddit' => 'reddit',
                'icons.brand_icons.ra' => 'ra',
                'icons.brand_icons.soundcloud' => 'soundcloud',
                'icons.brand_icons.spotify' => 'spotify',
                'icons.brand_icons.telegram' => 'telegram',
                'icons.brand_icons.tiktok' => 'tiktok',
                'icons.brand_icons.twitch' => 'twitch',
                'icons.brand_icons.twitter' => 'twitter',
                'Vimeo' => 'vimeo',
                'icons.brand_icons.whatsapp' => 'whatsapp',
                'icons.brand_icons.youtube' => 'youtube',
            ],
            'icons.common_icons_label' => [
                'icons.common_icons.bus' => 'bus',
                'icons.common_icons.calendar' => 'calendar',
                'icons.common_icons.coffee' => 'coffee',
                'icons.common_icons.comments' => 'comments',
                'icons.common_icons.cubes' => 'cubes',
                'icons.common_icons.download' => 'download',
                'icons.common_icons.email' => 'email',
                'icons.common_icons.heart' => 'heart',
                'icons.common_icons.home' => 'home',
                'icons.common_icons.image' => 'image',
                'icons.common_icons.link' => 'link',
                'icons.common_icons.location-dot' => 'location-dot',
                'icons.common_icons.map-pin' => 'map-pin',
                'icons.common_icons.music' => 'music',
                'icons.common_icons.pause' => 'pause',
                'icons.common_icons.phone' => 'phone',
                'icons.common_icons.play' => 'play',
                'icons.common_icons.rss' => 'rss',
                'icons.common_icons.smiley' => 'smiley',
                'icons.common_icons.star' => 'star',
                'icons.common_icons.ticket' => 'ticket',
                'icons.common_icons.upload' => 'upload',
                'icons.common_icons.user' => 'user',
                'icons.common_icons.video' => 'video',
            ],
        ];
        $builder
            ->add('url', UrlType::class, [
                'label' => 'url.form.url',
                'attr' => ['placeholder' => 'https://soundcloud.com/chipheadi'],
            ])
            ->add('icon', ChoiceType::class, [
                'label' => 'url.form.icon',
                'choices' => $iconChoices,
                'required' => false,
                'placeholder' => 'url.form.icon_placeholder',
                'attr' => [
                    'class' => 'icon-select form-select',
                ],
                'choice_attr' => fn ($choice, $key, $value): array => ['data-icon' => $choice],
            ])
            ->add('title', TextType::class, [
                'label' => 'url.form.title',
                'attr' => ['placeholder' => 'Soundcloud'],
            ])
            ->add('open_in_new_window', CheckboxType::class, [
                'label' => 'url.form.open_in_new_window',
            ]);
    }
}
