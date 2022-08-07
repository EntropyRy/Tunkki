<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class ContractAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('purpose')
            ->add('updatedAt')
            ->add('createdAt')
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('purpose')
            ->add('ContentFi', 'html')
            ->add('ContentEn')
            ->add('updatedAt')
            ->add('createdAt')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('purpose', ChoiceType::class, ['choices' => $this->getPurposeChoices()])
            ->add('ContentFi', CKEditorType::class, [
                'config' => ['full']
            ])
            ->add('ContentEn', CKEditorType::class, [
                'config' => ['full']
            ])
        ;
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('purpose')
            ->add('ContentFi')
            ->add('ContentEn')
            ->add('createdAt')
            ->add('updatedAt')
        ;
    }
    private function getPurposeChoices()
    {
        return [ 'Rent Contract' => 'rent' ];
    }
}
