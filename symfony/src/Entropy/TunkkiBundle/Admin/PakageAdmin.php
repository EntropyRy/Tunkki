<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

use Sonata\AdminBundle\Form\Type\ModelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PakageAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('rent')
//            ->add('needsFixing')
            ->add('notes')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name')
            ->add('rent')
            ->add('items')
            ->add('rentFromItems')
            ->add('whoCanRent')
  //          ->add('needsFixing')
            ->add('itemsNeedingFixing','array')
            ->add('notes')
            ->add('_action', null, array(
                'actions' => array(
                    'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                ),
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->with('Pakage')
            ->add('name')
            ->add('rent')
            ->add('whoCanRent', null, array('multiple'=>true, 'expanded' => true, 'by_reference' => false, 'help' => 'Select all fitting groups'))
    //        ->add('needsFixing')
            ->add('notes')
            ->add('items', ModelType::class, array('btn_add'=> false, 'multiple'=>true, 'expanded' => false, 'by_reference' => false))
            ->add('rentFromItems', TextType::class, array('disabled' => true))
            ->end()
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('items')
            ->add('rent')
      //      ->add('needsFixing')
            ->add('notes')
        ;
    }
}
