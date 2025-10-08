<?php

namespace App\Admin;

use App\Entity\Renter;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Validator\ErrorElement;

/**
 * @extends AbstractAdmin<Renter>
 *
 * TODO: Confirm the correct entity FQCN (e.g. \App\Entity\Renter). If the managed
 * entity differs, update this annotation accordingly.
 */
class RenterAdmin extends AbstractAdmin
{
    #[\Override]
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

    #[\Override]
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

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('name', null, ['required' => true])
            ->add('phone')
            ->add('email', null, ['required' => true])
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
        ;
    }

    #[\Override]
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
