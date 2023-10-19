<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;

class FileAdmin extends AbstractAdmin
{
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
                'actions' => ['edit' => [], 'delete' => []]
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('fileinfo')
            ->add('file', ModelListType::class, [
                'required' => false,
                'btn_delete' => 'unlink',
            ], [
                'link_parameters' => [
                    'context' => 'item'
                ]
            ]);
        if (!$this->isChild()) {
            $formMapper
                ->add('product');
        }
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
    }
}
