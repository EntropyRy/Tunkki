<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Doctrine\ORM\EntityManagerInterface;
use App\Helper\Mattermost;
use App\Form\TicketType;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventTicketController extends Controller
{
    public function presale(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TicketRepository $ticketRepo
    ): RedirectResponse {
        if ($event->ticketPresaleEnabled()) {
            $this->freeAvailableTickets($event, $ticketRepo);
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
    public function sale(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TicketRepository $ticketRepo
    ): RedirectResponse {
        if (!$event->ticketPresaleEnabled()) {
            $this->freeAvailableTickets($event, $ticketRepo);
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
    public function ticket(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        Mattermost $mm,
        #[MapEntity(mapping: ['reference' => 'referenceNumber'])]
        Ticket $ticket,
        TranslatorInterface $trans,
        NakkiBookingRepository $nakkirepo,
        EntityManagerInterface $em
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
        return $this->render('ticket.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $this->getNakkiFromGroup($event, $member, $selected, $request->getLocale()),
            'hasNakki' => count((array) $selected) > 0 ? true : false,
            'nakkiRequired' => $event->isNakkiRequiredForTicketReservation() ? true : false,
            'ticket' => $ticket,
            'form' => $form,
        ]);
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
            return $this->redirectToRoute('entropy_event_ticket', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'reference' => $ticket->getReferenceNumber()
            ]);
        }
        return null;
    }
    private function freeAvailableTickets($event, $ticketRepo): void
    {
        $now = new \DateTime('now');
        foreach ($event->getTickets() as $ticket) {
            if ($ticket->getStatus() == 'available' && !is_null($ticket->getOwner())) {
                if (($now->format('U') - $ticket->getUpdatedAt()->format('U')) >= 10800) {
                    $ticket->setOwner(null);
                    $ticketRepo->add($ticket, true);
                }
            }
        }
    }
    protected function getNakkiFromGroup($event, $member, $selected, $locale)
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
