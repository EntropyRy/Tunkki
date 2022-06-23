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
        TranslatorInterface $trans,
        TicketRepository $ticketRepo
    ): RedirectResponse
    {
        if($event->ticketPresaleEnabled()){
            $member = $this->getUser()->getMember();
            $ticket = $ticketRepo->findOneBy(['event' => $event, 'owner' => $member]);
            if (is_null($ticket)){
                $ticket = $ticketRepo->findAvailablePresaleTicket($event);
            }
            if (is_null($ticket)){
                $this->addFlash('warning', 'ticket.not_available');
            } else {
                return $this->redirectToRoute('entropy_event_ticket', [
                    'slug' => $event->getUrl(), 
                    'year' => $event->getEventDate()->format('Y'),
                    'reference' => $ticket->getReferenceNumber()
                ]);
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
     * @ParamConverter("ticket", options={"mapping": {"reference": "referenceNumber"}})
     */
    public function ticket(
        Request $request,
        Event $event,
        Mattermost $mm, 
        Ticket $ticket, 
        TranslatorInterface $trans,
        NakkiBookingRepository $nakkirepo
    ): Response
    {
        if ($ticket->getEvent() != $event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $member = $this->getUser()->getMember();
        $selected = $nakkirepo->findMemberEventBookings($member, $event);
        //$nakkis = $nakkirepo->findAvailebleBookings($member, $event);
        // käyttäjän varatut nakit + alueet
        // lipun tila, ref ja testit
        // Suosittelu
        return $this->render('ticket.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $this->getNakkis($event, $member, $request->getLocale())
            //'nakkis' => $nakkis
            //'form' => $form->createView(),
        ]);
    }
}
