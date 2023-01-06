<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use App\Entity\Event;
use App\Entity\Ticket;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class TicketAdminController extends CRUDController
{
    public function makePaidAction(TicketRepository $repo): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        if (is_null($ticket->getOwner())) {
            $this->addFlash('warning', 'ticket does not have owner!');
        } else {
            $ticket->setStatus('paid');
            $repo->add($ticket, true);
        }
        return $this->redirect($this->admin->generateUrl('list'));
    }
    public function updateTicketCountAction(Event $event, EntityManagerInterface $em): RedirectResponse
    {
        if ($event->getTicketsEnabled()) {
            $tickets_now = count($event->getTickets());
            $eventTicketCount = $event->getTicketCount();
            if ($eventTicketCount > 0) {
                $reqTickets = $eventTicketCount - $tickets_now;
                if ($tickets_now > $eventTicketCount) {
                    foreach ($event->getTickets() as $ticket) {
                        if (is_null($ticket->getOwner()) && $ticket->getStatus() == 'available') {
                            $em->remove($ticket);
                            $reqTickets += 1;
                        }
                        if ($reqTickets == 0) {
                            $em->flush();
                            $this->addFlash('success', 'Tickets removed');
                            break;
                        }
                    }
                    if ($reqTickets != 0) {
                        $this->addFlash('error', 'Cannot remove tickets because someone owns them and/or they are not available.');
                    }
                } else {
                    for ($i = 0; $i < $reqTickets; ++$i) {
                        $ticket = new Ticket();
                        $ticket->setEvent($event);
                        $ticket->setStatus('available');
                        $ticket->setPrice($event->getTicketPrice() ?: 0);
                        $em->persist($ticket);
                        $em->flush();
                        $ticket->setReferenceNumber($this->calculateReferenceNumber($ticket));
                        $em->persist($ticket);
                        $em->flush();
                    }
                    $this->addFlash('success', $reqTickets . ' tickets created');
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
        $id = (int)$ticket->getId() + 9000;
        $viite = (int)'909' . $id;

        for ($i = strlen($viite); $i > 0; $i--) {
            $summa += substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
        $cast = $viite . ((10 - ($summa % 10)) % 10);
        return (int)$cast;
    }
}
