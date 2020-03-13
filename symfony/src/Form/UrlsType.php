<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\AdminBundle\Form\Type\CollectionType;


final class UrlsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', TextType::class, ['help' => 'check the icon list at https://fontawesome.com/icons and submit here \'fab fa-facebook\' for example'])
            ->add('title', TextType::class)
            ->add('url', TextType::class)
            ;
    }
}
