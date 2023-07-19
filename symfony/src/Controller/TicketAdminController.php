<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChengeTicketOwnerType;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use App\Entity\Event;
use App\Entity\Ticket;
use App\Helper\ReferenceNumber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TicketAdminController extends CRUDController
{
    public function addBusAction(TicketRepository $repo): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        if (is_null($ticket->getOwner())) {
            $this->addFlash('warning', 'ticket does not have owner!');
        } else {
            $ticket->setStatus('paid_with_bus');
            $repo->save($ticket, true);
        }
        return $this->redirect($this->admin->generateUrl('list'));
    }
    public function changeOwnerAction(Request $request, TicketRepository $ticketRepo, NakkiBookingRepository $nakkiRepo): Response
    {
        $ticket = $this->admin->getSubject();
        if (is_null($ticket->getOwner())) {
            $this->addFlash('warning', 'ticket does not have owner!');
            return $this->redirect($this->admin->generateUrl('list'));
        } else {
            $nakki = $ticket->ticketHolderHasNakki();
            $form = $this->createForm(ChengeTicketOwnerType::class, $ticket, []);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $ticket = $form->getData();
                $new_owner = $ticket->getOwner();
                $info = '';
                if (!is_null($nakki)) {
                    $nakki->setMember($new_owner);
                    $nakkiRepo->save($nakki, true);
                    $info = 'Nakki and ';
                }
                $ticketRepo->save($ticket, true);
                $info .= 'Ticket moved to new member: ' . $new_owner;
                $this->addFlash('success', $info);
                return $this->redirect($this->admin->generateUrl('list'));
            }
        }
        return $this->renderWithExtraParams('admin/ticket/change_owner.html.twig', [
            'ticket' => $ticket,
            'form' => $form
        ]);
    }
    public function makePaidAction(TicketRepository $repo): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        if (is_null($ticket->getOwner())) {
            $this->addFlash('warning', 'ticket does not have owner!');
        } else {
            $ticket->setStatus('paid');
            $repo->save($ticket, true);
        }
        return $this->redirect($this->admin->generateUrl('list'));
    }
    public function updateTicketCountAction(Event $event, EntityManagerInterface $em, ReferenceNumber $rn): RedirectResponse
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
                        $ticket->setReferenceNumber($rn->calculateReferenceNumber($ticket, 9000, 909));
                        $em->persist($ticket);
                        $em->flush();
                    }
                    $this->addFlash('success', $reqTickets . ' tickets created');
                }
            }
        }
        return $this->redirect($this->admin->generateUrl('list'));
    }
}
