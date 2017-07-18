<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class BookingAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('items')
            ->add('invoicee')
            ->add('bookingDate')
            ->add('retrieval')
            ->add('returning')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name')
            ->add('invoicee')
            ->add('bookingDate')
            ->add('retrieval')
            ->add('returning')
            ->add('pakages')
            ->add('items')
            ->add('createdAt')
            ->add('modifiedAt')
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
            ->tab('General')
            ->with('Booking')
            ->add('name')
            ->add('bookingDate', 'sonata_type_date_picker')
            ->add('retrieval', 'sonata_type_datetime_picker')
            ->add('returning', 'sonata_type_datetime_picker')
            ->end()
            ->with('Rentals')
            ->add('items', null, array('expanded' => false))
            ->add('pakages', null, array('expanded' => true))
            ->add('invoicee', 'sonata_type_model_list', array('btn_delete' => 'Remove association'))
            ->end()
            ->end()
            ->tab('Meta')
            ->add('creator')
            ->add('createdAt')
            ->add('modifier')
            ->add('modifiedAt')
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
            ->add('invoicee')
            ->add('items')
            ->add('bookingDate')
            ->add('retrieval')
            ->add('returning')
            ->add('creator')
            ->add('createdAt')
            ->add('modifier')
            ->add('modifiedAt')
        ;
    }
}
