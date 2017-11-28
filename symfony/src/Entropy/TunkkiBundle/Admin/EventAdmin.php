<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class EventAdmin extends AbstractAdmin
{
    protected $parentAssociationMapping = 'product';
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
//            ->add('id')
            ->add('description')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        if (!$this->isChild()){
            $listMapper->add('product');
        }
        $listMapper
            ->add('description')
            ->add('creator')
            ->add('createdAt')
            ->add('_action', null, array(
                'actions' => array(
                    'show' => array(),
                    'edit' => array(),
     //               'delete' => array(),
                )
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        if (!$this->isChild()){
            $formMapper
                ->add('product')
            ;
        }
        $formMapper
            ->add('product.needsFixing')
            ->add('description','textarea', array('required' => false))
            ->add('creator', null, array('disabled' => true))
            ->add('createdAt','sonata_type_datetime_picker', array('disabled' => true))
        ;
        if (!$this->isChild()){
            $formMapper
                ->add('modifier', null, array('disabled' => true))
                ->add('updatedAt','sonata_type_datetime_picker', array('disabled' => true))
            ;
        }
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('product')
            ->add('description')
            ->add('creator')
            ->add('createdAt')
            ->add('modifier')
            ->add('updatedAt')
        ;
    }
    public function prePersist($Event)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $Event->setCreator($user);
        $Event->setModifier($user);
    }
    public function preUpdate($Event)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $Event->setModifier($user);
    }
}
