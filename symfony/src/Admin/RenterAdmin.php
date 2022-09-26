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
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
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

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []]])
        ;
    }

    protected function configureFormFields(FormMapper $formMapper): void
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

    protected function configureShowFields(ShowMapper $showMapper): void
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
    public function validate(ErrorElement $errorElement, $object): void
    {
        if (empty($object->getEmail())) {
            $errorElement->with('email')->addViolation('Email is needed for the billing')->end();
        }
    }
}
