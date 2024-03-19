<?php

namespace App\Controller;

use SimpleSoftwareIO\QrCode\Generator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\TicketType;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventTicketController extends Controller
{
    #[Route(
        path: [
            'en' => '/{year}/{slug}/ticket/presale',
            'fi' => '/{year}/{slug}/lippu/ennakkomyynti'
        ],
        name: 'entropy_event_ticket_presale',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function presale(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TicketRepository $ticketRepo,
        NakkiBookingRepository $nakkiBookingRepo
    ): RedirectResponse {
        if ($event->ticketPresaleEnabled()) {
            $this->freeAvailableTickets($event, $ticketRepo, $nakkiBookingRepo);
            $response = $this->ticketChecks('presale', $event, $ticketRepo);
            if (!is_null($response)) {
                return $response;
            }
        } else {
            $this->addFlash('warning', 'ticket.presale.off');
        }
        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $event->getUrl(),
            'year' => $event->getEventDate()->format('Y')
        ]);
    }
    #[Route(
        path: [
            'en' => '/{year}/{slug}/ticket/sale',
            'fi' => '/{year}/{slug}/lippu/myynti'
        ],
        name: 'entropy_event_ticket_sale',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function sale(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TicketRepository $ticketRepo,
        NakkiBookingRepository $nakkiBookingRepo
    ): RedirectResponse {
        if (!$event->ticketPresaleEnabled()) {
            $this->freeAvailableTickets($event, $ticketRepo, $nakkiBookingRepo);
            $response = $this->ticketChecks('sale', $event, $ticketRepo);
            if (!is_null($response)) {
                return $response;
            }
        }
        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $event->getUrl(),
            'year' => $event->getEventDate()->format('Y')
        ]);
    }
    #[Route(
        path: [
            'en' => '/{year}/{slug}/ticket/{reference}',
            'fi' => '/{year}/{slug}/lippu/{reference}'
        ],
        name: 'entropy_event_ticket',
        requirements: [
            'year' => '\d+',
            'reference' => '\d+',
        ]
    )]
    public function ticket(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        #[MapEntity(mapping: ['reference' => 'referenceNumber'])]
        Ticket $ticket,
        TranslatorInterface $trans,
        NakkiBookingRepository $nakkirepo,
        EntityManagerInterface $em,
    ): Response {
        if ($ticket->getEvent() != $event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if ($ticket->getOwner() != $member) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $selected = $nakkirepo->findMemberEventBookings($member, $event);
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $ticket->setStatus('reserved');
            $this->addFlash('success', 'ticket.reserved');
            $em->persist($ticket);
            $em->flush();
            return $this->redirectToRoute('entropy_event_ticket', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'reference' => $ticket->getReferenceNumber()
            ]);
        };
        $qr = new Generator();

        return $this->render('ticket/one.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $this->getNakkiFromGroup($event, $member, $selected, $request->getLocale()),
            'hasNakki' => count((array) $selected) > 0 ? true : false,
            'nakkiRequired' => $event->isNakkiRequiredForTicketReservation(),
            'ticket' => $ticket,
            'form' => $form,
            'qr' => base64_encode($qr
                ->format('png')
                ->style('round')
                ->eye('circle')
                ->margin(2)
                ->size(600)
                ->gradient(0, 40, 40, 40, 40, 0, 'radial')
                ->errorCorrection('H')
                ->merge('images/golden-logo.png', .2)
                ->generate((string)$ticket->getReferenceNumber()))
        ]);
    }

    #[Route(
        path: [
            'en' => '/{year}/{slug}/ticket/check',
            'fi' => '/{year}/{slug}/lippu/tarkistus'
        ],
        name: 'entropy_event_ticket_check',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function ticketCheck(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
    ): Response {
        return $this->render('ticket/check.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route(
        '/api/ticket/{id}/{referenceNumber}/info',
        name: '_entropy_event_ticket_api_check',
        requirements: [
            'id' => '\d+',
            'referenceNumner' => '\d+',
        ]
    )]
    public function ticketApiCheck(
        #[MapEntity(mapping: ['id' => 'id'])]
        Event $event,
        #[MapEntity(mapping: ['referenceNumber' => 'referenceNumber'])]
        Ticket $ticket,
    ): JsonResponse {
        if ($ticket->getEvent() == $event) {
            $ticketA = [
                'email' => $ticket->getOwner() ? $ticket->getOwnerEmail() : 'email',
                'status' => $ticket->getStatus(),
                'given' => $ticket->isGiven(),
                'referenceNumber' => $ticket->getReferenceNumber()
            ];
            $data = json_encode($ticketA);
        } else {
            $data = json_encode(['error' => 'not valid']);
        }
        return new JsonResponse($data);
    }

    #[Route(
        '/api/ticket/{id}/{referenceNumber}/give',
        name: '_entropy_event_ticket_api_give',
        requirements: [
            'referenceNumner' => '\d+',
        ]
    )]
    public function ticketApiGive(
        #[MapEntity(mapping: ['id' => 'id'])]
        Event $event,
        #[MapEntity(mapping: ['referenceNumber' => 'referenceNumber'])]
        Ticket $ticket,
        TicketRepository $ticketR
    ): JsonResponse {
        try {
            $ticket->setGiven(true);
            $ticketR->save($ticket, true);
            return new JsonResponse(json_encode(['ok' => 'TICKET_GIVEN_OUT']));
        } catch (\Exception $e) {
            return new JsonResponse(json_encode(['error' => $e->getMessage()]));
        }
    }

    private function ticketChecks($for, $event, $ticketRepo): ?RedirectResponse
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $ticket = $ticketRepo->findOneBy(['event' => $event, 'owner' => $member]);
        if (is_null($ticket)) {
            if ($for == 'presale') {
                $ticket = $ticketRepo->findAvailablePresaleTicket($event);
            } else {
                $ticket = $ticketRepo->findAvailableTicket($event);
            }
        }
        if (is_null($ticket)) {
            $this->addFlash('warning', 'ticket.not_available');
        } else {
            $ticket->setOwner($member);
            $ticketRepo->add($ticket, true);
            $this->addFlash('success', 'ticket.reserved_for_two_hours');
            return $this->redirectToRoute('entropy_event_ticket', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'reference' => $ticket->getReferenceNumber()
            ]);
        }
        return null;
    }
    private function freeAvailableTickets($event, $ticketRepo, $nakkiBookingRepo): void
    {
        $now = new \DateTime('now');
        foreach ($event->getTickets() as $ticket) {
            if ($ticket->getStatus() == 'available' && !is_null($ticket->getOwner())) {
                if (($now->format('U') - $ticket->getUpdatedAt()->format('U')) >= 10800) {
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
    }
    protected function getNakkiFromGroup($event, $member, $selected, $locale): array
    {
        $nakkis = [];
        foreach ($event->getNakkis() as $nakki) {
            foreach ($selected as $booking) {
                if ($booking->getNakki() == $nakki) {
                    $nakkis = $this->addNakkiToArray($nakkis, $booking, $locale);
                    break;
                }
            }
            if (!array_key_exists($nakki->getDefinition()->getName($locale), $nakkis)) {
                // try to prevent displaying same nakki to 2 different users using the system at the same time
                $bookings = $nakki->getNakkiBookings()->toArray();
                shuffle($bookings);
                foreach ($bookings as $booking) {
                    if (is_null($booking->getMember())) {
                        $nakkis = $this->addNakkiToArray($nakkis, $booking, $locale);
                        break;
                    }
                }
            }
        }
        return $nakkis;
    }
    protected function addNakkiToArray($nakkis, $booking, $locale): array
    {
        $name = $booking->getNakki()->getDefinition()->getName($locale);
        $duration = $booking->getStartAt()->diff($booking->getEndAt())->format('%h');
        $nakkis[$name]['description'] = $booking->getNakki()->getDefinition()->getDescription($locale);
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;
        return $nakkis;
    }
}
