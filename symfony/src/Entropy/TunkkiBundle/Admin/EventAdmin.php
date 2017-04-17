<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class EventAdmin extends AbstractAdmin
{
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
        $listMapper
  //          ->add('id')
            ->add('product')
            ->add('description')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator')
            ->add('_action', null, array(
                'actions' => array(
                    'show' => array(),
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
    //        ->add('id')
            ->add('description','textarea', array('required' => false))
//            ->add('updatedAt','sonata_type_datetime_picker', array('disabled' => true))
            ->add('creator', null, array('disabled' => true))
            ->add('createdAt','sonata_type_datetime_picker', array('disabled' => true))
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
      //      ->add('id')
            ->add('description')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator')
        ;
    }
    public function prePersist($Event)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
        $Event->setCreator($username);
    }
    public function preUpdate($Event)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
        $Event->setCreator($username);
    }
}
