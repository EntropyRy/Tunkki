<?php

declare(strict_types=1);

namespace App\Admin\Rental\Booking;

use App\Admin\Rental\AbstractRentalAdmin;
use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Entity\User;
use App\Form\Rental\Booking\ItemsType;
use App\Form\Rental\Booking\PackagesType;
use App\Repository\Rental\Booking\BookingRepository;
use App\Repository\Rental\Inventory\ItemRepository;
use App\Repository\Rental\Inventory\PackagesRepository;
use App\Service\MattermostNotifierService;
use App\Service\Rental\Booking\BookingReferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
// Forms
use Sonata\AdminBundle\Route\RouteCollectionInterface as RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\CollectionType;
use Sonata\Form\Type\DatePickerType;
use Sonata\Form\Type\DateRangePickerType;
use Sonata\Form\Type\DateTimePickerType;
// Entity
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @extends AbstractRentalAdmin<Booking>
 */
class BookingAdmin extends AbstractRentalAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(
        bool $isChildAdmin = false,
    ): string {
        return 'booking';
    }

    #[\Override]
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        // display the first page (default = 1)
        $sortValues[DatagridInterface::PAGE] = 1;

        // reverse order (default = 'ASC')
        $sortValues[DatagridInterface::SORT_ORDER] = 'DESC';

        // name of the ordered field (default = the model's id field, if any)
        $sortValues[DatagridInterface::SORT_BY] = 'bookingDate';
    }

    #[\Override]
    protected function configureTabMenu(
        MenuItemInterface $menu,
        $action,
        ?AdminInterface $childAdmin = null,
    ): void {
        if (!$childAdmin && !\in_array($action, ['edit', 'show'])) {
            return;
        }
        $admin = $this->isChild() ? $this->getParent() : $this;
        $request = $admin->getRequest();
        $id = $request->attributes->get('id');

        if ($this->isGranted('EDIT')) {
            $menu->addChild('Edit Booking', [
                'uri' => $admin->generateUrl('edit', ['id' => $id]),
            ]);
            $menu->addChild('Status', [
                'route' => 'admin_entropy_admin_booking_admin_entropy_admin_statusevent_create',
                'routeParameters' => [
                    'id' => $id,
                ],
            ]);
            $menu->addChild('Stufflist', [
                'uri' => $admin->generateUrl('stuffList', ['id' => $id]),
            ]);
            $object = $admin->getSubject();
            $renter = $object->getRenter();
            if ($renter instanceof Renter) {
                $routeName = Renter::ENTROPY_INTERNAL_ID === $renter->getId()
                    ? 'entropy_tunkki_booking_public_items'
                    : 'entropy_booking_hash';
                $routeParameters = [
                    'bookingid' => $id,
                    'hash' => $object->getRenterHash(),
                ];
                if ('entropy_booking_hash' === $routeName) {
                    $routeParameters['renterid'] = $renter->getId();
                }
                $menu->addChild('Contract', [
                    'route' => $routeName,
                    'routeParameters' => $routeParameters,
                ]);
            }
        }
    }

    #[\Override]
    protected function configureDatagridFilters(
        DatagridMapper $datagridMapper,
    ): void {
        $datagridMapper
            ->add('name')
            ->add('bookingDate', DateTimeRangeFilter::class, [
                'field_type' => DateRangePickerType::class,
            ])
            ->add('items')
            ->add('packages')
            ->add('renter')
            ->add('renterHash')
            ->add('retrieval', DateTimeRangeFilter::class, [
                'field_type' => DateTimeRangePickerType::class,
            ])
            ->add('givenAwayBy')
            ->add('returning', DateTimeRangeFilter::class, [
                'field_type' => DateTimeRangePickerType::class,
            ])
            ->add('receivedBy')
            ->add('itemsReturned')
            ->add('invoiceSent')
            ->add('paid');
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('name')
            ->add('renter')
            ->add('bookingDate')
            ->add('itemsReturned')
            ->add('paid')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'status' => [
                        'template' => 'admin/crud/list__action_status.html.twig',
                    ],
                    'stuffList' => [
                        'template' => 'admin/crud/list__action_stuff.html.twig',
                    ],
                    'edit' => [],
                    'delete' => [],
                    'removeSignature' => [
                        'template' => 'admin/crud/list__action_remove_signature.html.twig',
                    ],
                ],
            ]);
    }

    private function getCategories($choices = null): array
    {
        $slug = $this->cm->getBySlug('item');
        $root = $this->cm->getRootCategoryWithChildren($slug);
        // map categories
        $cats = [];
        foreach ($choices as $choice) {
            foreach ($root->getChildren() as $cat) {
                if ($choice->getCategory() == $cat) {
                    $cats[$cat->getName()][
                        $choice->getCategory()->getName()
                    ] = $choice;
                } elseif (
                    \in_array(
                        $choice->getCategory(),
                        $cat->getChildren()->toArray(),
                    )
                ) {
                    $cats[$cat->getName()][
                        $choice->getCategory()->getName()
                    ] = $choice;
                }
            }
        }

        return $cats;
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $subject = $this->getSubject();
        $bookings = null;
        $itemCats = null;
        $itemChoices = null;
        $packageChoices = null;
        if (!empty($subject->getName())) {
            $forWho = $subject->getRentingPrivileges();
            $bookings = $this->bookingRepository->findBookingsAtTheSameTime(
                $subject->getId(),
                $subject->getRetrieval(),
                $subject->getReturning(),
            );
            if (!empty($forWho)) {
                $packageChoices = $this->packagesRepository->getPackageChoicesWithPrivileges(
                    $forWho,
                );
                $itemChoices = $this->itemRepository->getItemChoicesWithPrivileges(
                    $forWho,
                );
                $itemCats = $this->getCategories($itemChoices);
            }
        }
        $changerewardownner = false;
        if ($subject->getPaid()) {
            $changerewardownner = true;
        }
        $formMapper
            ->tab('General')
            ->with('Booking', ['class' => 'col-md-6'])
            ->add('name', null, [
                'help' => 'Event name or name we use to talk about this case. Not the renters name!',
            ])
            ->add('bookingDate', DatePickerType::class, [
                'format' => 'd.M.y',
            ])
            ->add('retrieval', DateTimePickerType::class, [
                'required' => false,
                'format' => 'd.M.y H:mm',
                'datepicker_options' => [
                    'display' => [
                        'sideBySide' => true,
                        'components' => [
                            'seconds' => false,
                        ],
                    ],
                ],
                'label' => 'Pickup Time',
                'help' => 'This time needed to be determine if there is overlapping bookings for the same items.',
            ])
            ->add('givenAwayBy', null, [
                // ModelListType::class, [
                /*'btn_add' => false,
                 'btn_delete' => 'Unassign', */
                'required' => false,
                'disabled' => $changerewardownner,
            ])
            ->add('returning', DateTimePickerType::class, [
                'required' => false,
                'format' => 'd.M.y H:mm',
                'datepicker_options' => [
                    'display' => [
                        'sideBySide' => true,
                        'components' => [
                            'seconds' => false,
                        ],
                    ],
                ],
                'label' => 'Return Time',
                'help' => 'This time is needed to be determine if there is overlapping bookings for the same items.',
            ])
            ->add('receivedBy', null, [
                // ModelListType::class, [
                'required' => false,
                /*'btn_add' => false,
                 'btn_delete' => 'Unassign', */
                'disabled' => $changerewardownner,
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
        if (
            \is_object($subject->getRenter())
            && 1 == $subject->getRenter()->getId()
        ) {
            $formMapper
                ->tab('General')
                ->with('Who is Renting?', ['class' => 'col-md-6'])
                ->add('rentingPrivileges', null, [
                    'placeholder' => 'Show everything!',
                    'expanded' => true,
                ])
                ->end()
                ->end();
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
                    [
                        'required' => false,
                        'by_reference' => false,
                    ],
                    ['edit' => 'inline', 'inline' => 'table'],
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
                    'choices' => $itemChoices,
                ])
                ->add(
                    'accessories',
                    CollectionType::class,
                    ['required' => false, 'by_reference' => false],
                    ['edit' => 'inline', 'inline' => 'table'],
                )
                ->end()
                ->end();
        }

        if (!empty($subject->getName())) {
            $formMapper
                ->tab('Payment')
                ->with('Payment Information')
                ->add('referenceNumber', null, ['disabled' => true])
                ->add('calculatedTotalPrice', MoneyType::class, [
                    'disabled' => true,
                ])
                ->add('numberOfRentDays', null, [
                    'help' => 'How many days are actually billed',
                    'disabled' => false,
                    'required' => true,
                ])
                ->add('accessoryPrice', MoneyType::class, ['required' => false])
                ->add('actualPrice', MoneyType::class, [
                    'disabled' => false,
                    'required' => false,
                    'help' => 'If booking does not need to be billed: leave this empty',
                ])
                ->add('reasonForDiscount', null, [
                    'help' => 'If the actual price is discounted, let us know why',
                ])
                ->end()
                ->with('Events', ['class' => 'col-md-12'])
                ->add(
                    'billableEvents',
                    CollectionType::class,
                    ['required' => false, 'by_reference' => false],
                    ['edit' => 'inline', 'inline' => 'table'],
                )
                ->add('paid_date', DateTimePickerType::class, [
                    'disabled' => false,
                    'required' => false,
                ])
                ->end()
                ->end()
                ->tab('Meta')
                ->add('createdAt', DateTimePickerType::class, [
                    'disabled' => true,
                ])
                ->add('creator', null, ['disabled' => true])
                ->add('modifiedAt', DateTimePickerType::class, [
                    'disabled' => true,
                ])
                ->add('modifier', null, ['disabled' => true])
                ->end();
        }
    }

    #[\Override]
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
            ->add('createdAt')
            ->add('modifier')
            ->add('modifiedAt');
    }

    #[\Override]
    public function prePersist($booking): void
    {
        $user = $this->ts->getToken()->getUser();
        \assert($user instanceof User);
        $booking->setCreator($user);
    }

    #[\Override]
    public function postPersist($booking): void
    {
        $this->bookingRefService->assignReferenceAndHash($booking);
        $user = $this->ts->getToken()->getUser();
        \assert($user instanceof User);
        $text =
            '#### BOOKING: ['.
            $booking->getName().
            ']('.
            $this->generateUrl(
                'edit',
                ['id' => $booking->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ).
            ') on '.
            $booking->getBookingDate()->format('d.m.Y').
            ' created by '.
            $user;
        $this->mm->sendToMattermost($text, 'vuokraus');
        $this->update($booking);
        // $this->sendNotificationMail($booking);
    }

    #[\Override]
    public function preUpdate($booking): void
    {
        $this->bookingRefService->assignReferenceAndHash($booking);
        $user = $this->ts->getToken()->getUser();
        \assert($user instanceof User);
        $booking->setModifier($user);
    }

    #[\Override]
    public function preValidate(object $object): void
    {
        if (null != $object->getAccessories()) {
            foreach ($object->getAccessories() as $line) {
                if (null == $line->getCount() || null == $line->getName()) {
                    $session = $this->rs->getSession();
                    \assert($session instanceof Session);
                    $session
                        ->getFlashBag()
                        ->add(
                            'warning',
                            'Dont leave empty lines in accessories',
                        );
                }
            }
        }
        /*
        $errorElement
            ->with('bookingDate')
                ->assertNotNull([])
            ->end()
            ->with('renter')
                ->assertNotNull([])
            ->end()
        ;
        if ($object->getRetrieval() > $object->getReturning()) {
            $errorElement->with('retrieval')->addViolation('Must be before the returning')->end();
            $errorElement->with('returning')->addViolation('Must be after the retrieval')->end();
        }
        if (($object->getItemsReturned() == true) and ($object->getReceivedBy() == null)) {
            $errorElement->with('receivedBy')->addViolation('Who checked the rentals back to storage?')->end();
        }
         */
    }

    #[\Override]
    protected function configureRoutes(RouteCollection $collection): void
    {
        $collection->add(
            'removeSignature',
            $this->getRouterIdParameter().'/remove-signature',
        );
        $collection->add(
            'stuffList',
            $this->getRouterIdParameter().'/stufflist',
        );
        $collection->remove('delete');
    }

    public function __construct(
        protected MattermostNotifierService $mm,
        protected TokenStorageInterface $ts,
        protected EntityManagerInterface $em,
        protected CategoryManagerInterface $cm,
        protected RequestStack $rs,
        protected BookingRepository $bookingRepository,
        protected ItemRepository $itemRepository,
        protected PackagesRepository $packagesRepository,
        protected BookingReferenceService $bookingRefService,
    ) {
    }
}
