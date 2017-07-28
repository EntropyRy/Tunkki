<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class BookingAdmin extends AbstractAdmin
{
    protected $datagridValues = array(

        // display the first page (default = 1)
        '_page' => 1,

        // reverse order (default = 'ASC')
        '_sort_order' => 'DESC',

        // name of the ordered field (default = the model's id field, if any)
        '_sort_by' => 'createdAt',
    );

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
            ->addIdentifier('referenceNumber')
            ->add('name')
            ->add('invoicee')
            ->add('bookingDate')
            ->add('retrieval')
            ->add('returning')
            ->add('pakages')
            ->add('items')
            ->add('returned', null, array('editable' => true))
            ->add('paid', null, array('editable' => true))
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
            ->with('Booking', array('class' => 'col-md-3'))
            ->add('name')
            ->add('bookingDate', 'sonata_type_date_picker')
            ->add('retrieval', 'sonata_type_datetime_picker')
            ->add('returning', 'sonata_type_datetime_picker')
            ->end()
            ->with('Persons', array('class' => 'col-md-9'))
            ->add('invoicee', 'sonata_type_model_list', array('btn_delete' => 'Remove association'))
            ->add('giver', 'sonata_type_model_list', array('btn_add' => false, 'btn_delete' => 'Remove association'))
            ->end()
            ->end()
            ->tab('Rentals')
            ->with('The Stuff', array('class' => 'col-md-6'))
            ->add('items', null, array('expanded' => false, 'by_reference' => false))
            ->add('pakages', null, array('expanded' => true))
            ->add('rentInformation', 'textarea', array('disabled' => true))
            ->end()
            ->with('Payment Information', array('class' => 'col-md-6'))
            ->add('referenceNumber', null, array('disabled' => true))
            ->add('calculatedTotalPrice', 'text', array('disabled' => true))
            ->add('actualPrice', null, array('disabled' => false, 'required' => false))
            ->end()
            ->end()
            ->tab('Meta')
                ->add('returned')
                ->add('paid')
                ->add('paid_date', 'sonata_type_datetime_picker', array('disabled' => false, 'required' => false))
                ->add('createdAt', 'sonata_type_datetime_picker', array('disabled' => true))
                ->add('creator', null, array('disabled' => true))
                ->add('modifiedAt', 'sonata_type_datetime_picker', array('disabled' => true))
                ->add('modifier', null, array('disabled' => true))
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

    public function postPersist($booking)
    {
        $booking->setReferenceNumber($this->calculateReferenceNumber($booking));
    }

    protected function calculateReferenceNumber($booking)
    {
        $ki = 0;
        $summa = 0;
        $kertoimet = [7, 3, 1];
        $viite = '303'.$booking->getId();

        for ($i = strlen($viite); $i > 0; $i--) {
            $summa += substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
    
        return $viite.''.(10 - ($summa % 10)) % 10;
    }
    public function prePersist($booking)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $booking->setCreator($user);
        $booking->setActualPrice($booking->getCalculatedTotalPrice()*0.9);
    }    
    public function preUpdate($booking)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $booking->setModifier($user);
    }    
}
