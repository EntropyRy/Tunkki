<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use App\Entity\Event;
use App\Entity\Ticket;

final class TicketAdminController extends CRUDController
{
    public function makePaidAction()
    {
        $ticket = $this->admin->getSubject();
        if(is_null($ticket->getOwner())){
            $this->addFlash('warning', 'ticket does not have owner!');
        } else {
            $ticket->setStatus('paid');
            $ticketR = $this->getDoctrine()->getManager()->getRepository(Ticket::class);
            $ticketR->add($ticket, true);
        }
        return $this->redirect($this->admin->generateUrl('list'));
    }
    public function updateTicketCountAction(Event $event)
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
                    $this->addFlash('success', $new_tickets. ' tickets created');
                }
            }
        }
        return $this->redirect($this->admin->generateUrl('list'));
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
