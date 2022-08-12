<?php

namespace App\Controller;

use App\Controller\EventController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Security\Core\Security;
use App\Helper\Mattermost;
use App\Form\TicketType;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Entity\Event;
use App\Entity\Ticket;
use App\Entity\NakkiBooking;

/**
 * @IsGranted("ROLE_USER")
 */
class EventTicketController extends EventSignUpController
{
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function presale(
        Request $request,
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
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     */
    public function sale(
        Request $request,
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
    /**
     * @ParamConverter("event", class="App:Event", converter="event_year_converter")
     * @ParamConverter("ticket", options={"mapping": {"reference": "referenceNumber"}})
     */
    public function ticket(
        Request $request,
        Event $event,
        Mattermost $mm,
        Ticket $ticket,
        TranslatorInterface $trans,
        NakkiBookingRepository $nakkirepo
    ): Response {
        if ($ticket->getEvent() != $event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $member = $this->getUser()->getMember();
        if ($ticket->getOwner() != $member) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $selected = $nakkirepo->findMemberEventBookings($member, $event);
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $ticket->setStatus('reserved');
            $this->addFlash('success', 'ticket.reserved');
            $em = $this->getDoctrine()->getManager();
            $em->persist($ticket);
            $em->flush();
        };
        return $this->render('ticket.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $this->getNakkiFromGroup($event, $member, $selected, $request->getLocale()),
            'hasNakki' => count($selected)>0 ? true : false,
            'ticket' => $ticket,
            'form' => $form->createView(),
        ]);
    }
    private function ticketChecks($for, $event, $ticketRepo): ?RedirectResponse
    {
        $member = $this->getUser()->getMember();
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
}
