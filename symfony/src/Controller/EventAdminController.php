<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Ticket;

final class EventAdminController extends CRUDController
{
    public function artistListAction()
    {
        $event = $this->admin->getSubject();
        $infos = $event->getEventArtistInfos();
        return $this->renderWithExtraParams('admin/event/artist_list.html.twig', [
            'event' => $event, 
            'infos' => $infos
        ]);
    }
    public function nakkiListAction()
    {
        $event = $this->admin->getSubject();
        $nakkis = $event->getNakkiBookings();
        $emails = []; 
        foreach ($nakkis as $nakki){
            $member = $nakki->getMember();
            if ($member){
                $emails[$member->getId()] = $member->getEmail();
            }
        }
        $emails = implode(';', $emails);
        return $this->renderWithExtraParams('admin/event/nakki_list.html.twig', [
            'event' => $event, 
            'nakkiBookings' => $nakkis, 
            'emails' => $emails
        ]);
    }
    public function rsvpAction()
    {
        $event = $this->admin->getSubject();
        $rsvps = $event->getRSVPs();
        $email_url = $this->admin->generateUrl('rsvpEmail', ['id' => $event->getId()]);
        return $this->renderWithExtraParams('admin/event/rsvps.html.twig', [
            'event' => $event, 'rsvps' => $rsvps, 'email_url' => $email_url
        ]);
    }
    public function rsvpEmailAction()
    {
        $event = $this->admin->getSubject();
		$subject = $event->getRSVPEmailSubject();
		$body = $event->getRSVPEmailBody();
        $rsvps = $event->getRSVPs();
        if ($subject && $body) {
            foreach ($rsvps as $rsvp){
                $to = $rsvp->getAvailableEmail();
                $message = (new TemplatedEmail())
                    ->from(new Address('hallitus@entropy.fi', 'Entropyn Hallitus'))
                    ->to($to)
                    ->subject($subject)
                    ->htmlTemplate('emails/rsvp.html.twig')
                    ->context(['body' => $body ])
                ;
                $this->get('symfony.mailer')->send($message);
            } 
            $this->addFlash('sonata_flash_success', sprintf('%s RSVP info packages sent.', count($rsvps)));
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        } else {
            $this->addFlash('sonata_flash_error', sprintf('RSVP info packages NOT sent. Define email body and subject first.'));
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
    }
    public function preEdit(Request $request,$event)
    {
        if($event->getTicketsEnabled()){
            $tickets_now = count($event->getTickets());
            $req_tickets = $event->getTicketCount();
            if($req_tickets > 0){
                if($tickets_now > $req_tickets){
                    $this->addFlash('error', 'Cannot remove tickets. Please remove them manually.');
                } else {
                    $new_tickets = $req_tickets - $tickets_now;
                    $em = $this->getDoctrine()->getManager();
                    for ($i=0;$i<$new_tickets;++$i){
                        $ticket = new Ticket();
                        $ticket->setEvent($event);
                        $ticket->setStatus('available');
                        $ticket->setPrice($event->getTicketPrice()?$event->getTicketPrice():0);
                        $em->persist($ticket);
                        $em->flush();
                        $ticket->setReferenceNumber($this->calculateReferenceNumber($ticket));
                        $em->persist($ticket);
                        $em->flush();

                    }
                }
            }
        }
    }
    protected function calculateReferenceNumber($ticket): int
    {
        $ki = 0;
        $summa = 0;
        $kertoimet = [7, 3, 1];
        $id = (int)$ticket->getId()+9000;
        $viite = (int)'909'.$id;

        for ($i = strlen($viite); $i > 0; $i--) {
            $summa += substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
        $cast = $viite.((10 - ($summa % 10)) % 10);
        return (int)$cast;
    }
}

