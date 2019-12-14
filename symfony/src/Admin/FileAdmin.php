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
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('fileinfo')
            ->add('file')
        ;
        if(!$this->isChild()){
            $datagridMapper
                ->add('product')
            ;
        }
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('fileinfo')
            ->add('file', null, ['template' => 'EntropyTunkkiBundle:Admin:preview.html.twig'])
            ->add('downloadLink', 'html');
        if(!$this->isChild()){
            $listMapper->add('product');
        }
        $listMapper
            ->add('_action', null, array(
                'actions' => array(
//                    'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                )
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('fileinfo')
            ->add('file', ModelListType::class, array(
                'required' => false,
                'btn_delete' => 'unlink',
                    ), array(
                    'link_parameters' => array(
                        'context' => 'item'
                    )
            ))
        ;
        if(!$this->isChild()){
            $formMapper
                ->add('product')
            ;
        }
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
//            ->add('id')
//            ->add('tiedostoinfo')
        ;
    }
}
