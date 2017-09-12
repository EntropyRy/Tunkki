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
            ->add('pakages')
            ->add('invoicee')
            ->add('bookingDate', 'doctrine_orm_date_range',['field_type'=>'sonata_type_date_picker_range'])
            ->add('retrieval', 'doctrine_orm_datetime_range',['field_type'=>'sonata_type_datetime_range_picker'])
            ->add('returning', 'doctrine_orm_datetime_range',['field_type'=>'sonata_type_datetime_range_picker'])
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('referenceNumber')
            ->addIdentifier('name')
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
        $em = $this->modelManager->getEntityManager('Entropy\TunkkiBundle\Entity\Item');

        $subject = $this->getSubject();
        if (!empty($subject->getName())) {
            $forWho = $subject->getRentingPrivileges();
            $retrieval = $subject->getRetrieval();
            $returning = $subject->getReturning();
        }

        $items = $em->createQueryBuilder('c')
                ->select('c')
                ->from('EntropyTunkkiBundle:Item', 'c')
                ->where('c.needsFixing = false')
          //      ->andwhere('c.Retrieval < :retrieval')
          //      ->andwhere('c.Returning > :returning')
          //      ->setParameter('retrieval', $retrieval)
          //      ->setParameter('returning', $returning)
                
                ;
    
        $formMapper
            ->tab('General')
            ->with('Booking', array('class' => 'col-md-6'))
                ->add('name')
                ->add('bookingDate', 'sonata_type_date_picker')
                ->add('retrieval', 'sonata_type_datetime_picker', ['dp_side_by_side' => true])
                ->add('givenAwayBy', 'sonata_type_model_list', array('btn_add' => false, 'btn_delete' => 'unassign'))
                ->add('returning', 'sonata_type_datetime_picker', ['dp_side_by_side' => true])
                ->add('receivedBy', 'sonata_type_model_list', array('required' => false, 'btn_add' => false, 'btn_delete' => 'unassign'))
            ->end()
            ->with('Who is Renting?', array('class' => 'col-md-6'))
                ->add('invoicee', 'sonata_type_model_list', array('btn_delete' => 'unassign'))
                ->add('rentingPrivileges', null, array('help' => 'Only items that are in this group are shown'))
            ->end()
            ->end();

        $subject = $this->getSubject();
        if (!empty($subject->getName())) {
            $formMapper 
                ->tab('Rentals')
                ->with('The Stuff', array('class' => 'col-md-6'))
                    ->add('items', 'sonata_type_model', array(
                        'query' => $items, 
                        'multiple' => true, 
                        'expanded' => false, 
                        'by_reference' => false,
                        'btn_add' => false
                    ))
                    ->add('pakages', null, array( //'sonata_type_model', array(
 //                       'query' => $pakages, 
                        'multiple' => true, 
                        'expanded' => true, 
                        'by_reference' => false,
                       // 'btn_add' => false
                    ))
                    ->add('accessories', 'sonata_type_collection', array('required' => false, 'by_reference' => false),
                        array('edit' => 'inline', 'inline' => 'table')
                    )
                    ->add('rentInformation', 'textarea', array('disabled' => true))
                ->end()
                ->with('Payment Information', array('class' => 'col-md-6'))
                    ->add('referenceNumber', null, array('disabled' => true))
                    ->add('calculatedTotalPrice', 'text', array('disabled' => true))
                    ->add('numberOfRentDays', null, array('help' => 'How many days are actually billed', 'disabled' => false, 'required' => true))
                    ->add('actualPrice', null, array('disabled' => false, 'required' => false))
                ->end()
                ->end()
                ->tab('Events')
                ->with('Events', array('class' => 'col-md-12'))
                    ->add('returned')
                    ->add('billableEvents', 'sonata_type_collection', array('required' => false, 'by_reference' => false),
                        array('edit' => 'inline', 'inline' => 'table')
                    )
                    ->add('paid')
                    ->add('paid_date', 'sonata_type_datetime_picker', array('disabled' => false, 'required' => false))
                ->end()
                ->end()
                ->tab('Meta')
                    ->add('createdAt', 'sonata_type_datetime_picker', array('disabled' => true))
                    ->add('creator', null, array('disabled' => true))
                    ->add('modifiedAt', 'sonata_type_datetime_picker', array('disabled' => true))
                    ->add('modifier', null, array('disabled' => true))
                ->end()
            ;
        }
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('bookingDate')
            ->add('retrieval')
            ->add('returning')
            ->add('invoicee')
            ->add('items')
            ->add('pakages')
            ->add('creator')
            ->add('referenceNumber')
            ->add('actualPrice')
            ->add('returned')
            ->add('billableEvents')
            ->add('paid')
            ->add('paid_date')
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
  
    public function getFormTheme()
    {
        return array_merge(
            parent::getFormTheme(),
            array('EntropyTunkkiBundle:BookingAdmin:admin.html.twig')
        );
    } 
}
