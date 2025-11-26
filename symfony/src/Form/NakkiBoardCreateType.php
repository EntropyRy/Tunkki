<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\NakkiDefinition;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
final class NakkiBoardCreateType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $definitionsInUse = $options['definitions_in_use'];

        $builder
            ->add('definition', EntityType::class, [
                'class' => NakkiDefinition::class,
                'choice_label' => function (NakkiDefinition $definition) use ($definitionsInUse): string {
                    $label = $definition->getNameFi().' / '.$definition->getNameEn();
                    if (null !== $definition->getId()
                        && \in_array((string) $definition->getId(), $definitionsInUse, true)
                    ) {
                        $label .= ' '.$this->translator->trans('nakkikone.board.in_use_suffix');
                    }

                    return $label;
                },
                'choice_attr' => static fn (NakkiDefinition $definition): array => [
                    'data-in-use' => (null !== $definition->getId()
                        && \in_array((string) $definition->getId(), $definitionsInUse, true)) ? '1' : '0',
                ],
                'label' => 'nakkikone.board.definition_field',
                'placeholder' => 'nakkikone.board.select_definition',
                'translation_domain' => 'messages',
                'choice_translation_domain' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('responsible', MemberAutocompleteField::class, [
                'required' => false,
                'label' => 'nakkikone.column.responsible',
                'translation_domain' => 'messages',
                'placeholder' => 'nakkikone.board.choose_responsible',
            ])
            ->add('mattermostChannel', TextType::class, [
                'required' => false,
                'label' => 'nakkikone.column.mattermost',
                'translation_domain' => 'messages',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'definitions_in_use' => [],
            'allow_extra_fields' => true,
        ]);
    }
}
