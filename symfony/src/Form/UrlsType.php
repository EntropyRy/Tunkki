<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

final class UrlsType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'url.form.url',
                'attr' => ['placeholder' => 'https://soundcloud.com/entropy-fi'],
                'default_protocol' => 'https',
            ])
            ->add('icon', TextType::class, [
                'label' => 'url.form.icon',
                'help_html' => true,
                'help' => 'Most brands have icons corresponding their name. ex. Soundcloud -> soundcloud.<br>
                    Check: <a target=_blank href="https://github.com/EntropyRy/Tunkki/blob/main/symfony/config/packages/ux_icons.yaml#L14">preconfigured icon list</a><br>
                    And: <a target=_blank href="https://ux.symfony.com/icons">All icons</a>
                ',
            ])
            ->add('title', TextType::class, [
                'label' => 'url.form.title',
                'attr' => ['placeholder' => 'Soundcloud'],
            ])
            ->add('open_in_new_window', CheckboxType::class, [
                'required' => false,
                'label' => 'url.form.open_in_new_window',
            ]);
    }
}
