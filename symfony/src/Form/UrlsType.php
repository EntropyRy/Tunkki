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
            ->add('icon', TextType::class, [
                'label' => 'Icon Class',
                'empty_data' => 'fas fa-link',
                'help_html' => true, 
                'help' => 'Check the <a href="https://fontawesome.com/icons">icon list</a>. Example: \'fab fa-facebook\''])
            ->add('title', TextType::class)
            ->add('url', TextType::class)
            ;
    }
}
