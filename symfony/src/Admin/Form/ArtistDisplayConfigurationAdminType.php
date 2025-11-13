<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Entity\ArtistDisplayConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ArtistDisplayConfiguration>
 */
final class ArtistDisplayConfigurationAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                $builder->create('djTimetable', FormType::class, [
                    'inherit_data' => true,
                    'label' => 'DJ Timetable',
                    'row_attr' => ['class' => 'col-md-4'],
                    'label_attr' => ['class' => 'fw-bold text-uppercase d-block mb-2'],
                ])
                    ->add('djTimetableShowTime', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show times',
                    ])
                    ->add('djTimetableIncludePageLinks', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Link artist names to artist page',
                    ])
                    ->add('djTimetableShowGenre', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show genre',
                    ]),
            )
            ->add(
                $builder->create('djBio', FormType::class, [
                    'inherit_data' => true,
                    'label' => 'DJ Bio',
                    'row_attr' => ['class' => 'col-md-4'],
                    'label_attr' => ['class' => 'fw-bold text-uppercase d-block mb-2'],
                ])
                    ->add('djBioShowStage', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show stage heading',
                    ])
                    ->add('djBioShowPicture', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show artist picture',
                    ])
                    ->add('djBioShowTime', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show time',
                    ])
                    ->add('djBioShowGenre', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show genre',
                    ]),
            )
            ->add(
                $builder->create('vjTimetable', FormType::class, [
                    'inherit_data' => true,
                    'label' => 'VJ Timetable',
                    'row_attr' => ['class' => 'col-md-4'],
                    'label_attr' => ['class' => 'fw-bold text-uppercase d-block mb-2'],
                ])
                    ->add('vjTimetableShowTime', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show times',
                    ])
                    ->add('vjTimetableIncludePageLinks', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Link artist names to artist page',
                    ])
                    ->add('vjTimetableShowGenre', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show genre',
                    ]),
            )
            ->add(
                $builder->create('vjBio', FormType::class, [
                    'inherit_data' => true,
                    'label' => 'VJ Bio',
                    'row_attr' => ['class' => 'col-md-4'],
                    'label_attr' => ['class' => 'fw-bold text-uppercase d-block mb-2'],
                ])
                    ->add('vjBioShowStage', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show stage heading',
                    ])
                    ->add('vjBioShowPicture', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show artist picture',
                    ])
                    ->add('vjBioShowTime', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show time',
                    ])
                    ->add('vjBioShowGenre', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show genre',
                    ]),
            )
            ->add(
                $builder->create('artBio', FormType::class, [
                    'inherit_data' => true,
                    'label' => 'Art Bio',
                    'row_attr' => ['class' => 'col-md-4'],
                    'label_attr' => ['class' => 'fw-bold text-uppercase d-block mb-2'],
                ])
                    ->add('artBioShowStage', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show stage heading',
                    ])
                    ->add('artBioShowPicture', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show artist picture',
                    ])
                    ->add('artBioShowTime', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show time',
                    ])
                    ->add('artBioShowGenre', CheckboxType::class, [
                        'required' => false,
                        'label' => 'Show genre',
                    ]),
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArtistDisplayConfiguration::class,
            'label' => false,
            'attr' => ['class' => 'row gy-4'],
        ]);
    }
}
