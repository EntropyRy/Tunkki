<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Contract;
use App\Form\MarkdownEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeFilter;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractAdmin<Contract>
 */
final class ContractAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('purpose')
            ->add('validFrom', DateTimeFilter::class, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt')
            ->add('createdAt');
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('purpose')
            ->add('validFrom')
            ->add('updatedAt')
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('purpose', ChoiceType::class, [
                'choices' => $this->getPurposeChoices(),
                'choice_translation_domain' => 'messages',
            ])
            ->add('validFrom', DateTimePickerType::class, [
                'required' => false,
                'format' => 'd.M.y H:mm',
                'widget' => 'single_text',
                'datepicker_options' => [
                    'display' => [
                        'sideBySide' => true,
                        'components' => [
                            'seconds' => false,
                        ],
                    ],
                ],
            ])
            ->add('ContentFi', MarkdownEditorType::class, [
                'simple' => true,
            ])
            ->add('ContentEn', MarkdownEditorType::class, [
                'simple' => true,
            ]);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('purpose')
            ->add('validFrom')
            ->add('ContentFi')
            ->add('ContentEn')
            ->add('createdAt')
            ->add('updatedAt');
    }

    /**
     * @return array<string,string>
     */
    private function getPurposeChoices(): array
    {
        $choices = [];
        foreach (Contract::PURPOSES as $key => $value) {
            $choices['contract.purpose.'.$key] = $value;
        }

        return $choices;
    }
}
