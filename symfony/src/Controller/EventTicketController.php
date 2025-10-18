<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Service\NakkiDisplayService;
use App\Service\QrService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventTicketController extends Controller
{
    public function __construct(
        private readonly NakkiDisplayService $nakkiDisplay,
    ) {
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
            'nakkis' => $this->nakkiDisplay->getNakkiFromGroup(
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
}
