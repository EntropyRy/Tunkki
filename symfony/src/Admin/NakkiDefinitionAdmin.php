<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\NakkiDefinition;
use App\Form\MarkdownEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<NakkiDefinition>
 */
final class NakkiDefinitionAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
            ->add('onlyForActiveMembers')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
            ->add('onlyForActiveMembers')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('nameFi')
            ->add('DescriptionFi', MarkdownEditorType::class)
            ->add('nameEn')
            ->add('DescriptionEn', MarkdownEditorType::class)
            ->add('onlyForActiveMembers')
        ;
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('nameFi')
            ->add('DescriptionFi')
            ->add('nameEn')
            ->add('DescriptionEn')
            ->add('onlyForActiveMembers')
        ;
    }
}
