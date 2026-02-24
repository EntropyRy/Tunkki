<?php

declare(strict_types=1);

namespace App\Admin\Rental;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Inventory\Item;
use App\Entity\Rental\StatusEvent;
use App\Entity\User;
use App\Service\MattermostNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @extends AbstractRentalAdmin<StatusEvent>
 */
class StatusEventAdmin extends AbstractRentalAdmin
{
    /**
     * @var array<int, array<string, bool>>
     */
    private array $itemBeforeStates = [];

    /**
     * @var array<int, array<string, bool>>
     */
    private array $bookingBeforeStates = [];

    #[\Override]
    protected function generateBaseRoutePattern(
        bool $isChildAdmin = false,
    ): string {
        return 'status-event';
    }

    #[\Override]
    protected function configureDatagridFilters(
        DatagridMapper $datagridMapper,
    ): void {
        $datagridMapper
            ->add('item')
            ->add('booking')
            ->add('description')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator');
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        if (!$this->isChild()) {
            $listMapper->add('item');
            $listMapper->add('booking');
        }
        $listMapper
            ->add('description')
            ->add('creator')
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        if (!$this->isChild()) {
            $formMapper
                ->with('Item', ['class' => 'col-md-12'])
                ->add('item')
                ->end();
            $formMapper
                ->with('Booking', ['class' => 'col-md-12'])
                ->add('booking')
                ->end();
        }
        if (null != $this->getSubject()->getItem()) {
            $item = $this->getSubject()->getItem();
            \assert($item instanceof Item);

            $events = array_reverse(
                $item->getFixingHistory()->slice(0, 5),
            );
            $help = '';
            foreach ($events as $event) {
                $help .=
                    '['.
                    $event->getCreatedAt()->format('d.m.y H:i').
                    '] '.
                    $event->getCreator().
                    ': '.
                    $event->getDescription().
                    '<br>';
            }

            if ($item->getDecommissioned()) {
                $formMapper
                    ->with('Status', ['class' => 'col-md-4'])
                    ->add('item.decommissioned', CheckboxType::class, [
                        'required' => false,
                        'help' => 'Item is decommissioned. Other status flags are locked until this is unchecked.',
                    ])
                    ->end();
            } else {
                $formMapper
                    ->with('Status', ['class' => 'col-md-4'])
                    ->add('item.cannotBeRented', CheckboxType::class, [
                        'required' => false,
                    ])
                    ->add('item.needsFixing', CheckboxType::class, [
                        'required' => false,
                        'help' => 'Needs fixing does not automatically block renting.',
                    ])
                    ->add('item.forSale', CheckboxType::class, [
                        'required' => false,
                    ])
                    ->add('item.toSpareParts', CheckboxType::class, [
                        'required' => false,
                    ])
                    ->add('item.decommissioned', CheckboxType::class, [
                        'required' => false,
                    ])
                    ->end();
            }

            $formMapper
                ->with('Message', ['class' => 'col-md-8'])
                ->add('description', TextareaType::class, [
                    'required' => true,
                    'help' => $help,
                    'help_html' => true,
                ])
                ->end();
        }
        if (null != $this->getSubject()->getBooking()) {
            $formMapper
                ->with('Status', ['class' => 'col-md-4'])
                ->add('booking.cancelled', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('booking.renterConsent', CheckboxType::class, [
                    'required' => false,
                    'disabled' => true,
                ])
                ->add('booking.itemsReturned', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('booking.invoiceSent', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('booking.paid', CheckboxType::class, [
                    'required' => false,
                    'help' => 'please make sure booking handler has been selected',
                ])
                ->add('booking.givenAwayBy', null, ['disabled' => true])
                ->add('booking.receivedBy', null, ['disabled' => true])
                ->end()
                ->with('Message', ['class' => 'col-md-8'])
                ->add('description', TextareaType::class, [
                    'required' => true,
                    'help' => 'Describe in more detail. Will be visible for others in Mattermost.',
                ])
                ->end();
        }
        if (!$this->isChild()) {
            $formMapper
                ->with('Meta')
                ->add('creator', null, ['disabled' => true])
                ->add('createdAt', DateTimePickerType::class, [
                    'disabled' => true,
                ])
                ->add('modifier', null, ['disabled' => true])
                ->add('updatedAt', DateTimePickerType::class, [
                    'disabled' => true,
                ])
                ->end();
        }
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('item')
            ->add('booking')
            ->add('description')
            ->add('creator')
            ->add('createdAt')
            ->add('modifier')
            ->add('updatedAt');
    }

    #[\Override]
    public function prePersist($Event): void
    {
        \assert($Event instanceof StatusEvent);
        $user = $this->ts->getToken()->getUser();
        \assert($user instanceof User);
        $Event->setCreator($user);
        $Event->setModifier($user);
        $this->captureBeforeStates($Event);
        $this->enforceItemDecommissionedLock($Event);
    }

    #[\Override]
    public function postPersist($Event): void
    {
        \assert($Event instanceof StatusEvent);
        $user = $Event->getCreator();
        $text = $this->getMMtext($Event, $user);
        $this->mm->sendToMattermost($text, 'vuokraus');
        $this->clearTrackedStates($Event);
    }

    #[\Override]
    public function preUpdate($Event): void
    {
        \assert($Event instanceof StatusEvent);
        $user = $this->ts->getToken()->getUser();
        \assert($user instanceof User);
        $Event->setModifier($user);
        $this->captureBeforeStates($Event);
        $this->enforceItemDecommissionedLock($Event);
    }

    #[\Override]
    public function postUpdate($Event): void
    {
        \assert($Event instanceof StatusEvent);
        $user = $Event->getModifier();
        $text = $this->getMMtext($Event, $user);
        $this->mm->sendToMattermost($text, 'vuokraus');
        $this->clearTrackedStates($Event);
    }

    private function getMMtext(StatusEvent $Event, ?User $user): string
    {
        $url = $this->generateUrl(
            'show',
            ['id' => $Event->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        if ($Event->getItem() instanceof Item) {
            $thing = $Event->getItem();
            \assert($thing instanceof Item);
            $text = 'EVENT: ['.$thing->getName().']('.$url.') item status';

            $before = $this->itemBeforeStates[spl_object_id($Event)] ?? $this->snapshotItemStatus($thing);
            $after = $this->snapshotItemStatus($thing);
            $changes = $this->formatChanges($before, $after, $this->itemStatusLabels());

            if ([] === $changes) {
                $text .= ' unchanged';
            } else {
                $text .= ' changed: '.implode('; ', $changes);
            }
        } else {
            $thing = $Event->getBooking();
            \assert($thing instanceof Booking);
            $text = 'EVENT: ['.$thing->getName().']('.$url.') booking status';

            $before = $this->bookingBeforeStates[spl_object_id($Event)] ?? $this->snapshotBookingStatus($thing);
            $after = $this->snapshotBookingStatus($thing);
            $changes = $this->formatChanges($before, $after, $this->bookingStatusLabels());

            if ([] === $changes) {
                $text .= ' unchanged';
            } else {
                $text .= ' changed: '.implode('; ', $changes);
            }
        }
        if ($Event->getDescription()) {
            $text .= ' with comment: '.$Event->getDescription();
        }

        return $text.(' by '.($user instanceof User ? (string) $user : 'unknown'));
    }

    private function captureBeforeStates(StatusEvent $event): void
    {
        $key = spl_object_id($event);
        $uow = $this->em->getUnitOfWork();

        $item = $event->getItem();
        if ($item instanceof Item) {
            $original = $uow->getOriginalEntityData($item);
            if ([] !== $original) {
                $this->itemBeforeStates[$key] = [
                    'cannotBeRented' => (bool) ($original['cannotBeRented'] ?? $item->getCannotBeRented()),
                    'needsFixing' => (bool) ($original['needsFixing'] ?? $item->getNeedsFixing()),
                    'forSale' => (bool) ($original['forSale'] ?? $item->getForSale()),
                    'toSpareParts' => (bool) ($original['toSpareParts'] ?? $item->getToSpareParts()),
                    'decommissioned' => (bool) ($original['decommissioned'] ?? $item->getDecommissioned()),
                ];
            } else {
                $this->itemBeforeStates[$key] = $this->snapshotItemStatus($item);
            }
        }

        $booking = $event->getBooking();
        if ($booking instanceof Booking) {
            $original = $uow->getOriginalEntityData($booking);
            if ([] !== $original) {
                $this->bookingBeforeStates[$key] = [
                    'cancelled' => (bool) ($original['cancelled'] ?? $booking->getCancelled()),
                    'renterConsent' => (bool) ($original['renterConsent'] ?? $booking->getRenterConsent()),
                    'itemsReturned' => (bool) ($original['itemsReturned'] ?? $booking->getItemsReturned()),
                    'invoiceSent' => (bool) ($original['invoiceSent'] ?? $booking->getInvoiceSent()),
                    'paid' => (bool) ($original['paid'] ?? $booking->getPaid()),
                ];
            } else {
                $this->bookingBeforeStates[$key] = $this->snapshotBookingStatus($booking);
            }
        }
    }

    private function enforceItemDecommissionedLock(StatusEvent $event): void
    {
        $item = $event->getItem();
        if (!$item instanceof Item) {
            return;
        }

        $key = spl_object_id($event);
        $before = $this->itemBeforeStates[$key] ?? $this->snapshotItemStatus($item);
        $after = $this->snapshotItemStatus($item);

        if ($before['decommissioned'] || $after['decommissioned']) {
            $item->setCannotBeRented($before['cannotBeRented']);
            $item->setNeedsFixing($before['needsFixing']);
            $item->setForSale($before['forSale']);
            $item->setToSpareParts($before['toSpareParts']);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function snapshotItemStatus(Item $item): array
    {
        return [
            'cannotBeRented' => $item->getCannotBeRented(),
            'needsFixing' => $item->getNeedsFixing(),
            'forSale' => (bool) $item->getForSale(),
            'toSpareParts' => $item->getToSpareParts(),
            'decommissioned' => $item->getDecommissioned(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function snapshotBookingStatus(Booking $booking): array
    {
        return [
            'cancelled' => $booking->getCancelled(),
            'renterConsent' => $booking->getRenterConsent(),
            'itemsReturned' => $booking->getItemsReturned(),
            'invoiceSent' => $booking->getInvoiceSent(),
            'paid' => $booking->getPaid(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function itemStatusLabels(): array
    {
        return [
            'cannotBeRented' => 'cannot be rented',
            'needsFixing' => 'needs fixing',
            'forSale' => 'for sale',
            'toSpareParts' => 'to spare parts',
            'decommissioned' => 'decommissioned',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function bookingStatusLabels(): array
    {
        return [
            'cancelled' => 'cancelled',
            'renterConsent' => 'renter consent',
            'itemsReturned' => 'items returned',
            'invoiceSent' => 'invoice sent',
            'paid' => 'paid',
        ];
    }

    /**
     * @param array<string, bool>   $before
     * @param array<string, bool>   $after
     * @param array<string, string> $labels
     *
     * @return string[]
     */
    private function formatChanges(array $before, array $after, array $labels): array
    {
        $changes = [];
        foreach ($labels as $field => $label) {
            $old = (bool) ($before[$field] ?? false);
            $new = (bool) ($after[$field] ?? false);
            if ($old === $new) {
                continue;
            }

            $changes[] = $label.': '.$this->boolToWord($old).' -> '.$this->boolToWord($new);
        }

        return $changes;
    }

    private function boolToWord(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function clearTrackedStates(StatusEvent $event): void
    {
        $key = spl_object_id($event);
        unset($this->itemBeforeStates[$key], $this->bookingBeforeStates[$key]);
    }

    public function __construct(
        protected MattermostNotifierService $mm,
        protected TokenStorageInterface $ts,
        protected EntityManagerInterface $em,
    ) {
    }
}
