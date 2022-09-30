<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UrlsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'url.form.url',
                'attr' => ['placeholder' => 'https://soundcloud.com/entropy-fi']
            ])
            ->add('icon', TextType::class, [
                'label' => 'url.form.icon',
                'attr' => ['placeholder' => 'fab fa-soundcloud', 'data-icopy-target' => 'input'],
                'row_attr' => ['data-controller'=>'icopy'],
                'help_html' => true,
                'help' => 'Click: <a data-action="icopy#copyClass:prevent" data-icopy-iclass-param="fab fa-soundcloud" href="#"><i class="fab fa-soundcloud"></i></a> | <a data-action="icopy#copyClass:prevent" data-icopy-iclass-param="fab fa-facebook" href="#"><i class="fab fa-facebook"></i></a> | <a data-action="icopy#copyClass:prevent" data-icopy-iclass-param="fas fa-music" href="#"><i class="fas fa-music"></i></a> | Check the <a target=_blank href="https://fontawesome.com/icons">icon list</a>',
            ])
            ->add('title', TextType::class, [
                'label' => 'url.form.title',
                'attr' => ['placeholder' => 'Soundcloud']
            ])
            ->add('open_in_new_window', CheckboxType::class, [
                'required' => false,
                'label'=> 'url.form.open_in_new_window',
            ])
        ;
    }
}
