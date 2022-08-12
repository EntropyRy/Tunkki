<?php

namespace App\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Validator\ErrorElement;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// Forms
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\Form\Type\DateRangePickerType;
use Sonata\Form\Type\DateTimeRangePickerType;
use Sonata\Form\Type\DatePickerType;
use Sonata\Form\Type\CollectionType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use App\Form\ItemsType;
use App\Form\PackagesType;
// Entity
use App\Entity\Item;
use App\Entity\Booking;
use App\Entity\Package;
// Hash
use Hashids\Hashids;

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

    protected function configureSideMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null): void
    {
        if (!$childAdmin && !in_array($action, array('edit', 'show'))) {
            return;
        }
        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

        if ($this->isGranted('EDIT')) {
            $menu->addChild('Edit Booking', ['uri' => $admin->generateUrl('edit', ['id' => $id])]);
            $menu->addChild('Status', [
                'uri' => $admin->generateUrl('entropy_tunkki.admin.statusevent.create', ['id' => $id])
            ]);
            $menu->addChild('Stufflist', [
                'uri' => $admin->generateUrl('stuffList', ['id' => $id])
            ]);
            $object = $admin->getSubject();
            $menu->addChild('Contract', [
                'route' => 'entropy_tunkki_booking_hash',
                'routeParameters' => [
                     'bookingid'=> $id,
                     'renterid' => $object->getRenter()->getId(),
                     'hash' => $object->getRenterHash()
                ]
            ]);
        }
    }

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('name')
            ->add('bookingDate', DateTimeRangeFilter::class, ['field_type'=>DateRangePickerType::class])
            ->add('items')
            ->add('packages')
            ->add('renter')
            ->add('renterHash')
            ->add('retrieval', DateTimeRangeFilter::class, ['field_type'=>DateTimeRangePickerType::class])
            ->add('givenAwayBy')
            ->add('returning', DateTimeRangeFilter::class, ['field_type'=>DateTimeRangePickerType::class])
            ->add('receivedBy')
            ->add('itemsReturned')
            ->add('invoiceSent')
            ->add('paid')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('name')
            ->add('renter')
            ->add('bookingDate')
            ->add('itemsReturned')
            ->add('paid')
            ->add('_action', null, array(
                'actions' => array(
                    'status' => array(
                        'template' => 'admin/crud/list__action_status.html.twig'
                    ),
                    'stuffList' => array(
                        'template' => 'admin/crud/list__action_stuff.html.twig'
                    ),
            //        'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                ),
            ))
        ;
    }
    private function getCategories($choices = null): array
    {
        $root = $this->cm->getRootCategory('item');
        // map categories
        $cats = [];
        foreach ($choices as $choice) {
            foreach ($root->getChildren() as $cat) {
                if ($choice->getCategory() == $cat) {
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                } elseif (in_array($choice->getCategory(), $cat->getChildren()->toArray())) {
                    $cats[$cat->getName()][$choice->getCategory()->getName()]=$choice;
                }
            }
        }
        return $cats;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $subject = $this->getSubject();
        $bookings = null;
        $itemCats = null;
        $itemChoices = null;
        $packageChoices = null;
        if (!empty($subject->getName())) {
            $forWho = $subject->getRentingPrivileges();
            $bookingsrepo = $this->em->getRepository(Booking::class);
            $bookings = $bookingsrepo->findBookingsAtTheSameTime($subject->getId(), $subject->getRetrieval(), $subject->getReturning());
            if (!empty($forWho)) {
                $packageChoices = $this->em->getRepository(Package::class)->getPackageChoicesWithPrivileges($forWho);
                $itemChoices = $this->em->getRepository(Item::class)->getItemChoicesWithPrivileges($forWho);
                $itemCats = $this->getCategories($itemChoices);
            }
        }
        $changerewardownner = false;
        if ($subject->getPaid()) {
            $changerewardownner = true;
        }
        $formMapper
            ->tab('General')
            ->with('Booking', array('class' => 'col-md-6'))
                ->add('name', null, ['help' => "Event name or name we use to talk about this case."])
                ->add('bookingDate', DatePickerType::class, [
                        'format' => 'd.M.y',
                    ])
                ->add('retrieval', DateTimePickerType::class, [
                        'required' => false,
                        'format' => 'd.M.y H:mm',
                        'dp_side_by_side' => true,
                        'dp_use_seconds' => false,
                        'label' => 'Pickup Time'
                    ])
                ->add('givenAwayBy', null, [ // ModelListType::class, [
                        /*'btn_add' => false,
                        'btn_delete' => 'Unassign', */
                        'required' => false,
                        'disabled' => $changerewardownner
                    ])
                ->add('returning', DateTimePickerType::class, [
                        'required' => false,
                        'format' => 'd.M.y H:mm',
                        'dp_side_by_side' => true,
                        'dp_use_seconds' => false,
                        'label' => 'Return Time'
                    ])
                ->add('receivedBy', null, [ //ModelListType::class, [
                        'required' => false,
                        /*'btn_add' => false,
                        'btn_delete' => 'Unassign', */
                        'disabled' => $changerewardownner
                    ])
            ->end()
            ->with('Who is Renting?', ['class' => 'col-md-6'])
                ->add('renter', ModelListType::class, ['btn_delete' => 'Unassign'])
                ->add('rentingPrivileges', null, [
                    'placeholder' => false,
                    'expanded' => true,
                ])
                ->add('renterHash', null, ['disabled' => true])
            ->end()
            ->end();
        if (is_object($subject->getRenter()) && $subject->getRenter()->getId() == 1) {
            $formMapper
            ->tab('General')
            ->with('Who is Renting?', ['class' => 'col-md-6'])
                ->add('rentingPrivileges', null, [
                    'placeholder' => 'Show everything!',
                    'expanded' => true,
                ])
                ->end()
                ->end()
            ;
        }

        if (!empty($subject->getName()) && empty($forWho)) {
            $formMapper
                ->tab('Rentals')
                ->with('The Stuff')
                    ->add('packages', PackagesType::class, [
                        'bookings' => $bookings,
                    ])
                    ->add('items', ItemsType::class, [
                        'bookings' => $bookings,
                    ])
                    ->add(
                        'accessories',
                        CollectionType::class,
                        ['required' => false, 'by_reference' => false],
                        ['edit' => 'inline', 'inline' => 'table']
                    )
                ->end()
                ->end();
        } elseif (!empty($subject->getName()) && !empty($forWho)) {
            $formMapper
                ->tab('Rentals')
                ->with('The Stuff')
                    ->add('packages', PackagesType::class, [
                        'bookings' => $bookings,
                        'choices' => $packageChoices,
                    ])
                    ->add('items', ItemsType::class, [
                        'bookings' => $bookings,
                        'categories' => $itemCats,
                        'choices' => $itemChoices
                    ])
                    ->add(
                        'accessories',
                        CollectionType::class,
                        ['required' => false, 'by_reference' => false],
                        ['edit' => 'inline', 'inline' => 'table']
                    )
                ->end()
                ->end();
        }

        if (!empty($subject->getName())) {
            $formMapper
                ->tab('Payment')
                ->with('Payment Information')
                    ->add('referenceNumber', null, ['disabled' => true])
                    ->add('calculatedTotalPrice', TextType::class, ['disabled' => true])
                    ->add('numberOfRentDays', null, [
                        'help' => 'How many days are actually billed',
                        'disabled' => false,
                        'required' => true
                        ])
                    ->add('actualPrice', null, [
                        'disabled' => false,
                        'required' => false,
                        'help' => 'If booking does not need to be billed: leave this empty'
                    ])
                    ->add('reasonForDiscount', null, ['help' => 'If the actual price is discounted, let us know why'])
                ->end()
                ->with('Events', array('class' => 'col-md-12'))
                    ->add(
                        'billableEvents',
                        CollectionType::class,
                        array('required' => false, 'by_reference' => false),
                        array('edit' => 'inline', 'inline' => 'table')
                    )
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
    protected function configureShowFields(ShowMapper $showMapper): void
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
            ->add('itemsReturned')
            ->add('billableEvents')
            ->add('paid')
            ->add('paid_date')
            ->add('creator')
            ->add('creatededAt')
            ->add('modifier')
            ->add('modifiedAt')
        ;
    }

    protected function calculateOwnerHash($booking): string
    {
        $hashids = new Hashids($booking->getName().$booking->getRenter(), 10);
        return strtolower($hashids->encode($booking->getReferenceNumber()));
    }
    protected function calculateReferenceNumber($booking): int
    {
        $ki = 0;
        $summa = 0;
        $kertoimet = [7, 3, 1];
        $id = (int)$booking->getId()+1220;
        $viite = (int)'303'.$id;

        for ($i = strlen($viite); $i > 0; $i--) {
            $summa += substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
        return $viite.''.(10 - ($summa % 10)) % 10;
    }
    public function prePersist($booking): void
    {
        $user = $this->ts->getToken()->getUser();
        $booking->setCreator($user);
    }
    public function postPersist($booking): void
    {
        $booking->setReferenceNumber($this->calculateReferenceNumber($booking));
        $booking->setRenterHash($this->calculateOwnerHash($booking));
        $user = $this->ts->getToken()->getUser();
        $text = '#### BOOKING: <'.$this->generateUrl(
            'edit',
            ['id'=> $booking->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ).'|'.$booking->getName().'> on '.
            $booking->getBookingDate()->format('d.m.Y').' created by '.$user;
        $this->mm->SendToMattermost($text, 'vuokraus');
        //$this->sendNotificationMail($booking);
    }
    public function preUpdate($booking): void
    {
        if ($booking->getReferenceNumber() == null || $booking->getReferenceNumber() == 0) {
            $booking->setReferenceNumber($this->calculateReferenceNumber($booking));
        }
        if ($booking->getRenterHash() == null || $booking->getRenterHash() == 0) {
            $booking->setRenterHash($this->calculateOwnerHash($booking));
        }
        $user = $this->ts->getToken()->getUser();
        $booking->setModifier($user);
    }

    public function getFormTheme(): array
    {
        $themes = array_merge(
            parent::getFormTheme(),
            array('admin/booking/_edit_rentals.html.twig')
        );
        return $themes;
    }
    public function validate(ErrorElement $errorElement, $object): void
    {
        $errorElement
            ->with('bookingDate')
                ->assertNotNull(array())
            ->end()
            ->with('renter')
                ->assertNotNull(array())
            ->end()
        ;
        if ($object->getRetrieval() > $object->getReturning()) {
            $errorElement->with('retrieval')->addViolation('Must be before the returning')->end();
            $errorElement->with('returning')->addViolation('Must be after the retrieval')->end();
        }
        if (($object->getItemsReturned() == true) and ($object->getReceivedBy() == null)) {
            $errorElement->with('receivedBy')->addViolation('Who checked the rentals back to storage?')->end();
        }
        if ($object->getAccessories() != null) {
            foreach ($object->getAccessories() as $line) {
                if ($line->getCount() == null and $line->getName() == null) {
                    $errorElement->with('accessories')->addViolation('Dont leave empty lines in accessories')->end();
                }
            }
        }
    }
    protected function configureRoutes(RouteCollection $collection): void
    {
        $collection->add('stuffList', $this->getRouterIdParameter().'/stufflist');
        $collection->remove('delete');
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
