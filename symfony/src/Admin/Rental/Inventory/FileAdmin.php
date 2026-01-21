<?php

declare(strict_types=1);

namespace App\Admin\Rental\Inventory;

use App\Admin\Rental\AbstractRentalAdmin;
use App\Entity\Rental\Inventory\File;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractRentalAdmin<File>
 */
class FileAdmin extends AbstractRentalAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(
        bool $isChildAdmin = false,
    ): string {
        return 'file';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('fileinfo')
            ->add('file');
        if (!$this->isChild()) {
            $datagridMapper
                ->add('product');
        }
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('fileinfo')
            ->add('file', null, ['template' => 'admin/item/_image_preview.html.twig'])
            ->add('downloadLink', 'html');
        if (!$this->isChild()) {
            $listMapper->add('product');
        }
        $listMapper
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['edit' => [], 'delete' => []],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('fileinfo')
            ->add('file', ModelListType::class, [
                'required' => false,
                'btn_delete' => 'unlink',
            ], [
                'link_parameters' => [
                    'context' => 'item',
                ],
            ]);
        if (!$this->isChild()) {
            $formMapper
                ->add('product');
        }
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
    }
}
