<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Sonata\AdminBundle\Form\Type\CollectionType;


final class UrlsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', TextType::class, [
                'label' => 'Icon Class',
                'help_html' => true, 
                'help' => 'Check the <a target=_blank href="https://fontawesome.com/icons">icon list</a>. Example: \'fab fa-facebook\''])
            ->add('title', TextType::class)
            ->add('url', UrlType::class)
            ;
    }
}
