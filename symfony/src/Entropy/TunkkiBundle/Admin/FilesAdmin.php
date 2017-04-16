<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class FilesAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
//            ->add('id')
            ->add('fileinfo')
            ->add('file')
            ->add('product')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
  //          ->add('id')
            ->addIdentifier('fileinfo')
            ->add('file')
            ->add('download', 'html')
            ->add('product')
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
            //->add('tuote')
            ->add('file', 'sonata_type_model_list', array(
                'required' => false,
                'btn_delete' => false,
                'btn_list' => false,
                    ), array(
                    'link_parameters' => array(
                        'context' => 'item'
                    )
            ))
//            ->add('download', 'url', array('route' => 'sonata_media_download', 'parameters' => array('id'=>2 )))
        ;
        if (!$this->hasParentFieldDescription()){
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
            ->add('tiedostoinfo')
        ;
    }
}
