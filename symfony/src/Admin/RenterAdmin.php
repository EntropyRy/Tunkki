<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Validator\ErrorElement;

class RenterAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
            ->add('phone')
            ->add('email')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
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
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
        ;
    }
    public function validate(ErrorElement $errorElement, $object)
    {
        if (empty($object->getEmail())) {
            $errorElement->with('email')->addViolation('Email is needed for the billing')->end();
        }
    }
}
