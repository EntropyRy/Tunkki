<?php

namespace Entropy\TunkkiBundle\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\CoreBundle\Validator\ErrorElement;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// Forms
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\CoreBundle\Form\Type\DateTimePickerType;
use Sonata\CoreBundle\Form\Type\DateRangePickerType;
use Sonata\CoreBundle\Form\Type\DateTimeRangePickerType;
use Sonata\CoreBundle\Form\Type\DatePickerType;
use Sonata\CoreBundle\Form\Type\CollectionType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ChoiceFieldMaskType;
use Entropy\TunkkiBundle\Form\Type\ItemsType;
use Entropy\TunkkiBundle\Form\Type\PackagesType;
// Entity
use Entropy\TunkkiBundle\Entity\Item;
use Entropy\TunkkiBundle\Entity\Booking;
use Entropy\TunkkiBundle\Entity\Package;


class BookingAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'booking';
    protected $mm; // Mattermost helper
    protected $ts; // Token Storage
    protected $em; // E manager
    protected $cm; // Category manager

    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'createdAt',
    ];

    protected function configureSideMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null)
    {
        if (!$childAdmin && !in_array($action, array('edit', 'show'))) {
            return;
        }
        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');
    
//        $menu->addChild('View Item', array('uri' => $admin->generateUrl('show', array('id' => $id))));

        if ($this->isGranted('EDIT')) {
            $menu->addChild('Edit Booking', ['uri' => $admin->generateUrl('edit', ['id' => $id])]);
            $menu->addChild('Show Stufflist', [
                'uri' => $admin->generateUrl('stuffList', ['id' => $id])
            ]);
        }
    }

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('items')
            ->add('packages')
            ->add('renter')
            ->add('bookingDate', 'doctrine_orm_date_range',['field_type'=>DateRangePickerType::class])
            ->add('retrieval', 'doctrine_orm_datetime_range',['field_type'=>DateTimeRangePickerType::class])
            ->add('returning', 'doctrine_orm_datetime_range',['field_type'=>DateTimeRangePickerType::class])
            ->add('givenAwayBy')
            ->add('receivedBy')
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
            ->add('renter')
            ->add('bookingDate')
            ->add('retrieval')
            ->add('returning')
            ->add('returned')
            ->add('paid')
            ->add('_action', null, array(
                'actions' => array(
                    'stuffList' => array(
                        'template' => 'EntropyTunkkiBundle:CRUD:list__action_stuff.html.twig'
                    ),
            //        'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                ),
            ))
        ;
    }
    private function getCategories($choices = null)
    {
        $root = $this->cm->getRootCategory('item');
        // map categories
        foreach($choices as $choice) {
            foreach($root->getChildren() as $cat) {
                if($choice->getCategory() == $cat){
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                }
                elseif (in_array($choice->getCategory(), $cat->getChildren()->toArray())){
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                }
            }
        }
        return $cats;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $subject = $this->getSubject();
        if (!empty($subject->getName())) {
            $forWho = $subject->getRentingPrivileges();
            $bookingsrepo = $this->em->getRepository(Booking::class);
            $bookings = $bookingsrepo->findBookingsAtTheSameTime($subject->getId(), $subject->getRetrieval(), $subject->getReturning());
            if(!empty($forWho)){
                $packageChoices = $this->em->getRepository(Package::class)->getPackageChoicesWithPrivileges($forWho);
                $itemChoices = $this->em->getRepository(Item::class)->getItemChoicesWithPrivileges($forWho);
                $itemCats = $this->getCategories($itemChoices);
            } 
        }
        $formMapper
            ->tab('General')
            ->with('Booking', array('class' => 'col-md-6'))
                ->add('name')
                ->add('bookingDate', DatePickerType::class, [
                        'format' => 'd.M.y',
                    ])
                ->add('retrieval', DateTimePickerType::class, [
                        'required' => false,
                        'format' => 'd.M.y H:mm',
                        'dp_side_by_side' => true,
                        'dp_use_seconds' => false,
                    ])
                ->add('givenAwayBy', ModelListType::class, [
                        'btn_add' => false,
                        'btn_delete' => 'unassign', 
                        'required' => false
                    ])
                ->add('returning', DateTimePickerType::class, [
                        'required' => false,
                        'format' => 'd.M.y H:mm',
                        'dp_side_by_side' => true, 
                        'dp_use_seconds' => false, 
                    ])
                ->add('receivedBy', ModelListType::class, [
                        'required' => false, 
                        'btn_add' => false, 
                        'btn_delete' => 'unassign'
                    ])
                ->add('returned')
            ->end()
            ->with('Who is Renting?', array('class' => 'col-md-6'))
                ->add('renter', ModelListType::class, ['btn_delete' => 'unassign'])
                ->add('rentingPrivileges', null, [
                    'placeholder' => 'Show everything!'
                    ])
            ->end()
            ->end();

        if (!empty($subject->getName())) {
            $formMapper 
                ->tab('Rentals')
                ->with('The Stuff');
        }

        if (!empty($subject->getName()) && empty($forWho)) {
            $formMapper 
                    ->add('packages', PackagesType::class, [
                        'bookings' => $bookings,
                    ])
                    ->add('items', ItemsType::class, [
                        'bookings' => $bookings,
                    ]);
        } elseif (!empty($subject->getName()) && !empty($forWho)) {
            $formMapper 
                    ->add('packages', PackagesType::class, [ 
                        'bookings' => $bookings,
                        'choices' => $packageChoices, 
                    ])
                    ->add('items', ItemsType::class, [
                        'bookings' => $bookings,
                        'categories' => $itemCats,
                        'choices' => $itemChoices
                    ]);
        }

        if (!empty($subject->getName())){
            $formMapper 
                ->add('accessories', CollectionType::class, 
                        ['required' => false, 'by_reference' => false],
                        ['edit' => 'inline', 'inline' => 'table']
                    )
                ->end()
                ->end()
                ->tab('Payment')
                ->with('Payment Information')
                    ->add('referenceNumber', null, ['disabled' => true])
                    ->add('calculatedTotalPrice', TextType::class, ['disabled' => true])
                    ->add('numberOfRentDays', null, [
                        'help' => 'How many days are actually billed', 
                        'disabled' => false, 
                        'required' => true
                        ])
                    ->add('actualPrice', null, ['disabled' => false, 'required' => false])
                ->end()
                ->with('Events', array('class' => 'col-md-12'))
                    ->add('billableEvents', CollectionType::class, array('required' => false, 'by_reference' => false),
                        array('edit' => 'inline', 'inline' => 'table')
                    )
                    ->add('paid')
                    ->add('paid_date', DateTimePickerType::class, array('disabled' => false, 'required' => false))
                ->end()
                ->end()
                ->tab('Meta')
                    ->add('createdAt', DateTimePickerType::class, ['disabled' => true])
                    ->add('creator', null, ['disabled' => true])
                    ->add('modifiedAt', DateTimePickerType::class, ['disabled' => true])
                    ->add('modifier', null, ['disabled' => true])
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
            ->add('renter')
            ->add('items')
            ->add('packages')
            ->add('accessories')
            ->add('referenceNumber')
            ->add('actualPrice')
            ->add('returned')
            ->add('billableEvents')
            ->add('paid')
            ->add('paid_date')
            ->add('creator')
            ->add('creatededAt')
            ->add('modifier')
            ->add('modifiedAt')
        ;
    }


    protected function calculateReferenceNumber($booking)
    {
        $ki = 0;
        $summa = 0;
        $kertoimet = [7, 3, 1];
        $viite = '303'.($booking->getId()+1220);

        for ($i = strlen($viite); $i > 0; $i--) {
            $summa += substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
        return $viite.''.(10 - ($summa % 10)) % 10;
    }
    public function prePersist($booking)
    {
        $user = $this->ts->getToken()->getUser();
        $booking->setCreator($user);
    }
    public function postPersist($booking)
    {
        $booking->setReferenceNumber($this->calculateReferenceNumber($booking));
        $user = $this->ts->getToken()->getUser();
        $text = '#### BOOKING: <'.$this->generateUrl('edit', ['id'=> $booking->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL).'|'.$booking->getName().'> on '.
            $booking->getBookingDate()->format('d.m.Y').' created by '.$user;
        $this->mm->SendToMattermost($text);
    }
    public function preUpdate($booking)
    {
        $user = $this->ts->getToken()->getUser();
        $booking->setModifier($user);
    }

    public function getFormTheme()
    {
        $themes = array_merge(
            parent::getFormTheme(),
            array('EntropyTunkkiBundle:BookingAdmin:admin.html.twig')
        );
        return $themes;
    }
    public function validate(ErrorElement $errorElement, $object)
    {
        $errorElement
            ->with('bookingDate')
                ->assertNotNull(array())
            ->end()
            ->with('renter')
                ->assertNotNull(array())
            ->end()
        ;
        if($object->getRetrieval() > $object->getReturning()){
            $errorElement->with('retrieval')->addViolation('Must be before the returning')->end();
            $errorElement->with('returning')->addViolation('Must be after the retrieval')->end();
        }
        if(($object->getReturned() == true) and ($object->getReceivedBy() == null)){
            $errorElement->with('receivedBy')->addViolation('Who checked the rentals back to storage?')->end();
        }
        foreach ($object->getAccessories() as $line){
            if($line->getCount() == NULL and $line->getName() == NULL){
                $errorElement->with('accessories')->addViolation('Dont leave empty lines in accessories')->end();
            }
        }
    } 
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('stuffList', $this->getRouterIdParameter().'/stufflist');
    }
    public function __construct($code, $class, $baseControllerName, $mm=null, $ts=null, $em=null, $cm=null)
    {
        $this->mm = $mm;
        $this->ts = $ts;
        $this->em = $em;
        $this->cm = $cm;
        parent::__construct($code, $class, $baseControllerName);
    }
}
