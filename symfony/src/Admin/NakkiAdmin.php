<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\AdminBundle\Route\RouteCollection;
use App\Entity\NakkiBooking;
use Sonata\AdminBundle\Form\Type\ModelListType;

final class NakkiAdmin extends AbstractAdmin
{
    protected $em; // EntityManager
    protected $ts; // Token Storage
    protected $fl; // FlashBag
    protected $mm; // Mattermost Helper

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
        ;
    }

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

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('definition', ModelListType::class);
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
                'with_hours' => true
            ])
            ->add('startAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
                'format' => 'd.M.y, H:00',
                'dp_use_seconds' => false,
                'dp_use_minutes' => false,
                'minutes' => 00,
                'dp_side_by_side' => true,
            ])
            ->add('endAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
                'format' => 'd.M.y, H:00',
                'minutes' => 00,
                'dp_use_seconds' => false,
                'dp_use_minutes' => false,
                'dp_side_by_side' => true,
            ])
        ;
    }

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
    public function postPersist($nakki): void
    {
        // create booking nakkis
        $diff = $nakki->getStartAt()->diff($nakki->getEndAt());
        $hours = $diff->h;
        $hours = ($hours + ($diff->days*24)) / $nakki->getNakkiInterval()->format('%h');
        for ($i = 0; $i < $hours; $i++) {
            $this->createBooking($nakki, $i);
        }
        $this->em->flush();
    }
    public function postUpdate($nakki): void
    {
        $bookings = $nakki->getNakkiBookings();
        $diff = $nakki->getStartAt()->diff($nakki->getEndAt());
        foreach ($bookings as $booking) {
            if ($booking->getMember()) {
                $this->fl->add('error', 'One or more Nakki has been reserved by member. Edit Nakki bookings manually. Nothing changed');
                return;
            }
        }
        $this->postDelete($nakki);
        $this->postPersist($nakki);
    }
    public function postDelete($nakki): void
    {
        $bookings = $this->em->getRepository('App:NakkiBooking')->findBy(['nakki'=>$nakki]);
        foreach ($bookings as $b) {
            $this->em->remove($b);
        }
        $this->em->flush();
    }

    public function __construct($code, $class, $baseControllerName, $mm=null, $ts=null, $em=null, $flash=null)
    {
        $this->mm = $mm;
        $this->ts = $ts;
        $this->em = $em;
        $this->fl = $flash;
        parent::__construct($code, $class, $baseControllerName);
    }

    protected function createBooking($nakki, $i): void
    {
        $b = new NakkiBooking();
        $b->setNakki($nakki);
        $start = $i*$nakki->getNakkiInterval()->format('%h');
        $b->setStartAt($nakki->getStartAt()->modify($start.' hour'));
        $end = $start+$nakki->getNakkiInterval()->format('%h');
        $b->setEndAt($nakki->getStartAt()->modify($end.' hour'));
        $b->setEvent($nakki->getEvent());
        $this->em->persist($b);
    }
    protected function configureRoutes(RouteCollection $collection): void
    {
        $collection->add('clone', $this->getRouterIdParameter().'/clone');
    }
}
