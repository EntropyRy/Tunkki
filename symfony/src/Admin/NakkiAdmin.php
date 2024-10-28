<?php

declare(strict_types=1);

namespace App\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Validator\ErrorElement;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use App\Entity\NakkiBooking;
use App\Helper\Mattermost;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class NakkiAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'nakki';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('definition');
        if (!$this->isChild()) {
            $filter
                ->add('event');
        }
        $filter
            ->add('responsible')
            ->add('startAt')
            ->add('endAt')
            ->add('disableBookings')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('definition');
        if (!$this->isChild()) {
            $list
                ->add('event');
        }
        $list
            ->add('responsible')
            ->add('startAt')
            ->add('endAt')
            ->add('disableBookings')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'clone' => [
                        'template' => 'admin/crud/list__action_clone.html.twig'
                    ],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('definition', ModelListType::class, [
                'required' => true
            ]);
        if (!$this->isChild()) {
            $form
                ->add('event');
        }
        $form
            ->add('responsible')
            ->add('mattermostChannel')
            ->add('nakkiInterval', null, [
                'with_years' => false,
                'with_months' => false,
                'with_days' => false,
                'with_hours' => true,
                'required' => true
            ])
            ->add('startAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
                'format' => 'd.M.y, H:00',
                'datepicker_options' => [
                    'display' => [
                        'sideBySide' => true,
                        'components' => [
                            'seconds' => false,
                        ]
                    ]
                ],
                'minutes' => 00,
            ])
            ->add('endAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
                'format' => 'd.M.y, H:00',
                'datepicker_options' => [
                    'display' => [
                        'sideBySide' => true,
                        'components' => [
                            'seconds' => false,
                        ]
                    ]
                ],
                'minutes' => 00,
            ])
            ->add('disableBookings')
        ;
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('definition')
            ->add('event')
            ->add('responsible')
            ->add('startAt')
            ->add('endAt')
        ;
    }
    #[\Override]
    public function postPersist($nakki): void
    {
        // create booking nakkis
        $diff = $nakki->getStartAt()->diff($nakki->getEndAt());
        $hours = $diff->h;
        $hours = ($hours + ($diff->days * 24)) / $nakki->getNakkiInterval()->format('%h');
        for ($i = 0; $i < $hours; $i++) {
            $this->createBooking($nakki, $i);
        }
        $this->em->flush();
    }
    #[\Override]
    public function postUpdate($nakki): void
    {
        $bookings = $nakki->getNakkiBookings();
        // $diff = $nakki->getStartAt()->diff($nakki->getEndAt());
        foreach ($bookings as $booking) {
            if ($booking->getMember()) {
                $session = $this->rs->getSession();
                assert($session instanceof Session);
                $session->getFlashBag()->add('error', 'One or more Nakki has been reserved by member. Edit Nakki bookings manually. Nothing changed');
                return;
            }
        }
        $this->postDelete($nakki);
        $this->postPersist($nakki);
    }
    public function postDelete($nakki): void
    {
        $bookings = $this->em->getRepository(NakkiBooking::class)->findBy(['nakki' => $nakki]);
        foreach ($bookings as $b) {
            $this->em->remove($b);
        }
        $this->em->flush();
    }

    public function __construct(
        protected Mattermost $mm,
        protected TokenStorageInterface $ts,
        protected EntityManagerInterface $em,
        protected RequestStack $rs
    ) {
    }

    protected function createBooking($nakki, $i): void
    {
        $b = new NakkiBooking();
        $b->setNakki($nakki);
        $start = $i * $nakki->getNakkiInterval()->format('%h');
        $b->setStartAt($nakki->getStartAt()->modify($start . ' hour'));
        $end = $start + $nakki->getNakkiInterval()->format('%h');
        $b->setEndAt($nakki->getStartAt()->modify($end . ' hour'));
        $b->setEvent($nakki->getEvent());
        $this->em->persist($b);
    }
    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('clone', $this->getRouterIdParameter() . '/clone');
    }
    public function validate(ErrorElement $errorElement, $object): void
    {
        $errorElement
            ->with('definition')
            ->assertNotNull(['definition cannot be null'])
            ->end();
    }
}
