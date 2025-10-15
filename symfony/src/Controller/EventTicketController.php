<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Service\QrService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventTicketController extends Controller
{
    #[
        Route(
            path: [
                'en' => '/{year}/{slug}/ticket/presale',
                'fi' => '/{year}/{slug}/lippu/ennakkomyynti',
            ],
            name: 'entropy_event_ticket_presale',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function presale(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        TicketRepository $ticketRepo,
        NakkiBookingRepository $nakkiBookingRepo,
    ): RedirectResponse {
        if ($event->ticketPresaleEnabled()) {
            $this->freeAvailableTickets($event, $ticketRepo, $nakkiBookingRepo);
            $response = $this->ticketChecks('presale', $event, $ticketRepo);
            if ($response instanceof RedirectResponse) {
                return $response;
            }
        } else {
            $this->addFlash('warning', 'ticket.presale.off');
        }

        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $event->getUrl(),
            'year' => $event->getEventDate()->format('Y'),
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/{year}/{slug}/ticket/sale',
                'fi' => '/{year}/{slug}/lippu/myynti',
            ],
            name: 'entropy_event_ticket_sale',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function sale(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        TicketRepository $ticketRepo,
        NakkiBookingRepository $nakkiBookingRepo,
    ): RedirectResponse {
        if (!$event->ticketPresaleEnabled()) {
            $this->freeAvailableTickets($event, $ticketRepo, $nakkiBookingRepo);
            $response = $this->ticketChecks('sale', $event, $ticketRepo);
            if ($response instanceof RedirectResponse) {
                return $response;
            }
        }

        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $event->getUrl(),
            'year' => $event->getEventDate()->format('Y'),
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/{year}/{slug}/ticket/{reference}',
                'fi' => '/{year}/{slug}/lippu/{reference}',
            ],
            name: 'entropy_event_ticket',
            requirements: [
                'year' => "\d+",
                'reference' => "\d+",
            ],
        ),
    ]
    public function ticket(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        #[
            MapEntity(mapping: ['reference' => 'referenceNumber']),
        ]
        Ticket $ticket,
        TranslatorInterface $trans,
        NakkiBookingRepository $nakkirepo,
        EntityManagerInterface $em,
        QrService $qr,
    ): Response {
        if ($ticket->getEvent() != $event) {
            throw new NotFoundHttpException($trans->trans('event_not_found'));
        }
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if ($ticket->getOwner() !== $member) {
            throw new NotFoundHttpException($trans->trans('event_not_found'));
        }
        $nakkirepo->findMemberEventBookings($member, $event);

        return $this->render('ticket/one.html.twig', [
            'event' => $event,
            'ticket' => $ticket,
            'qr' => $qr->getQrBase64((string) $ticket->getReferenceNumber()),
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/{year}/{slug}/tickets',
                'fi' => '/{year}/{slug}/liput',
            ],
            name: 'entropy_event_tickets',
            requirements: [
                'year' => "\d+",
                'reference' => "\d+",
            ],
        ),
    ]
    public function tickets(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        TranslatorInterface $trans,
        NakkiBookingRepository $nakkirepo,
        EntityManagerInterface $em,
        TicketRepository $ticketRepo,
        QrService $qr,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $qrs = [];
        $selected = $nakkirepo->findMemberEventBookings($member, $event);
        $tickets = $ticketRepo->findBy(['event' => $event, 'owner' => $member]);

        foreach ($tickets as $ticket) {
            $qrs[] = [
                'qr' => $qr->getQrBase64(
                    (string) $ticket->getReferenceNumber(),
                ),
                'name' => $ticket->getName() ?? 'Ticket',
            ];
        }
        // check that event does not have other type of tickets available
        // if so, provide link to the shop page
        $showShop = false;
        $products = $event->getTicketProducts();
        if (\count($products) > 1) {
            $userTickets = $ticketRepo->findTicketsByEmailAndEvent(
                $member->getEmail(),
                $event,
            );
            if (\count($userTickets) < \count($products)) {
                $showShop = true;
            }
        } else {
            $showShop = true;
        }

        return $this->render('ticket/multiple.html.twig', [
            'event' => $event,
            'selected' => $selected,
            'nakkis' => $this->getNakkiFromGroup(
                $event,
                $member,
                $selected,
                $request->getLocale(),
            ),
            'hasNakki' => [] !== (array) $selected,
            'nakkiRequired' => $event->isNakkiRequiredForTicketReservation(),
            'tickets' => $tickets,
            'showShop' => $showShop,
            'qrs' => $qrs,
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/{year}/{slug}/ticket/check',
                'fi' => '/{year}/{slug}/lippu/tarkistus',
            ],
            name: 'entropy_event_ticket_check',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function ticketCheck(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        return $this->render('ticket/check.html.twig', [
            'event' => $event,
        ]);
    }

    #[
        Route(
            '/api/ticket/{id}/{referenceNumber}/info',
            name: '_entropy_event_ticket_api_check',
            requirements: [
                'id' => "\d+",
                'referenceNumner' => "\d+",
            ],
        ),
    ]
    public function ticketApiCheck(
        #[MapEntity(mapping: ['id' => 'id'])] Event $event,
        #[
            MapEntity(mapping: ['referenceNumber' => 'referenceNumber']),
        ]
        Ticket $ticket,
    ): JsonResponse {
        if ($ticket->getEvent() == $event) {
            $ticketA = [
                'email' => $ticket->getOwner() instanceof Member
                        ? $ticket->getOwnerEmail()
                        : 'email',
                'status' => $ticket->getStatus(),
                'given' => $ticket->isGiven(),
                'referenceNumber' => $ticket->getReferenceNumber(),
            ];
            $data = json_encode($ticketA);
        } else {
            $data = json_encode(['error' => 'not valid']);
        }

        return new JsonResponse($data);
    }

    #[
        Route(
            '/api/ticket/{id}/{referenceNumber}/give',
            name: '_entropy_event_ticket_api_give',
            requirements: [
                'referenceNumner' => "\d+",
            ],
        ),
    ]
    public function ticketApiGive(
        #[MapEntity(mapping: ['id' => 'id'])] Event $event,
        #[
            MapEntity(mapping: ['referenceNumber' => 'referenceNumber']),
        ]
        Ticket $ticket,
        TicketRepository $ticketR,
    ): JsonResponse {
        try {
            $ticket->setGiven(true);
            $ticketR->save($ticket, true);

            return new JsonResponse(json_encode(['ok' => 'TICKET_GIVEN_OUT']));
        } catch (\Exception $e) {
            return new JsonResponse(json_encode(['error' => $e->getMessage()]));
        }
    }

    private function ticketChecks(
        string $for,
        Event $event,
        TicketRepository $ticketRepo,
    ): ?RedirectResponse {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $ticket = $ticketRepo->findOneBy([
            'event' => $event,
            'owner' => $member,
        ]);
        if (null === $ticket) {
            $ticket =
                'presale' === $for
                    ? $ticketRepo->findAvailablePresaleTicket($event)
                    : $ticketRepo->findAvailableTicket($event);
        }
        if (null === $ticket) {
            $this->addFlash('warning', 'ticket.not_available');
        } else {
            $ticket->setOwner($member);
            $ticketRepo->add($ticket, true);
            $this->addFlash('success', 'ticket.reserved_for_two_hours');

            return $this->redirectToRoute('entropy_event_ticket', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'reference' => $ticket->getReferenceNumber(),
            ]);
        }

        return null;
    }

    private function freeAvailableTickets(
        Event $event,
        TicketRepository $ticketRepo,
        NakkiBookingRepository $nakkiBookingRepo,
    ): void {
        $now = new \DateTimeImmutable('now');
        foreach ($event->getTickets() as $ticket) {
            if (
                'available' == $ticket->getStatus()
                && null !== $ticket->getOwner()
                && $now->format('U') - $ticket->getUpdatedAt()->format('U') >=
                    10800
            ) {
                if ($event->isNakkiRequiredForTicketReservation()) {
                    foreach ($event->getNakkiBookings() as $nakkiB) {
                        if ($nakkiB->getMember() == $ticket->getOwner()) {
                            $nakkiB->setMember(null);
                            $nakkiBookingRepo->save($nakkiB, true);
                        }
                    }
                }
                $ticket->setOwner(null);
                $ticketRepo->add($ticket, true);
            }
        }
    }

    protected function getNakkiFromGroup(
        $event,
        $member,
        $selected,
        $locale,
    ): array {
        $nakkis = [];
        foreach ($event->getNakkis() as $nakki) {
            if (true == $nakki->isDisableBookings()) {
                continue;
            }
            foreach ($selected as $booking) {
                if ($booking->getNakki() == $nakki) {
                    $nakkis = $this->addNakkiToArray(
                        $nakkis,
                        $booking,
                        $locale,
                    );
                    break;
                }
            }
            if (
                !\array_key_exists(
                    $nakki->getDefinition()->getName($locale),
                    $nakkis,
                )
            ) {
                // try to prevent displaying same nakki to 2 different users using the system at the same time
                $bookings = $nakki->getNakkiBookings()->toArray();
                shuffle($bookings);
                foreach ($bookings as $booking) {
                    if (null === $booking->getMember()) {
                        $nakkis = $this->addNakkiToArray(
                            $nakkis,
                            $booking,
                            $locale,
                        );
                        break;
                    }
                }
            }
        }

        return $nakkis;
    }

    protected function addNakkiToArray(array $nakkis, $booking, $locale): array
    {
        $name = $booking->getNakki()->getDefinition()->getName($locale);
        $duration = $booking
            ->getStartAt()
            ->diff($booking->getEndAt())
            ->format('%h');
        $nakkis[$name]['description'] = $booking
            ->getNakki()
            ->getDefinition()
            ->getDescription($locale);
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;

        return $nakkis;
    }
}
