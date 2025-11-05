<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\NakkiDefinition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<NakkiDefinition>
 */
final class NakkiDefinitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nameFi', TextType::class, [
                'label' => 'nakkikone.definition.name_fi',
                'translation_domain' => 'messages',
            ])
            ->add('nameEn', TextType::class, [
                'label' => 'nakkikone.definition.name_en',
                'translation_domain' => 'messages',
            ])
            ->add('descriptionFi', TextareaType::class, [
                'label' => 'nakkikone.definition.description_fi',
                'required' => false,
                'attr' => ['rows' => 4],
                'translation_domain' => 'messages',
            ])
            ->add('descriptionEn', TextareaType::class, [
                'label' => 'nakkikone.definition.description_en',
                'required' => false,
                'attr' => ['rows' => 4],
                'translation_domain' => 'messages',
            ])
            ->add('onlyForActiveMembers', CheckboxType::class, [
                'label' => 'nakkikone.definition.only_active',
                'required' => false,
                'translation_domain' => 'messages',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NakkiDefinition::class,
        ]);
    }
}
