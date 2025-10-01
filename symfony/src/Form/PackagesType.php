<?php

namespace App\Form;

use App\Entity\Package;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PackagesType extends AbstractType
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['bookings'] = $options['bookings'];
        $view->vars['btn_add'] = $options['btn_add'];
        $view->vars['btn_list'] = $options['btn_list'];
        $view->vars['btn_delete'] = $options['btn_delete'];
        $view->vars['btn_catalogue'] = $options['btn_catalogue'];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Package::class,
            'required' => false,
            'choices' => null,
            'bookings' => [],
            'by_reference' => false,
            'compound' => true,
            'multiple' => true,
            'expanded' => true,
            'btn_add' => 'link_add',
            'btn_list' => 'link_list',
            'btn_delete' => 'link_delete',
            'btn_catalogue' => 'SonataAdminBundle',
        ]);
    }

    #[\Override]
    public function getParent(): string
    {
        return EntityType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'entropy_type_packages';
    }
}
