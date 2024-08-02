<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChengeTicketOwnerType;
use App\Repository\EmailRepository;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Helper\Qr;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

final class TicketAdminController extends CRUDController
{
    public function giveAction(TicketRepository $repo): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        if (is_null($ticket->getOwner())) {
            $this->addFlash('warning', 'ticket does not have owner!');
        } else {
            $ticket->setGiven(true);
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
        return $this->render('admin/ticket/change_owner.html.twig', [
            'ticket' => $ticket,
            'form' => $form
        ]);
    }
    public function sendQrCodeEmailAction(EmailRepository $emailRepo, MailerInterface $mailer): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        $to = $ticket->getEmail();
        $email = $emailRepo->findOneBy(['purpose' => 'ticket_qr', 'event' => $ticket->getEvent()]);
        $replyTo = 'hallitus@entropy.fi';
        $body = '';
        if ($email != null) {
            $replyTo = $email->getReplyTo() ?? 'hallitus@entropy.fi';
            $body = $email->getBody();
        }
        $qrGenerator = new Qr();
        $qr = [
            'qr' => $qrGenerator->getQr((string)$ticket->getReferenceNumber()),
            'name' => $ticket->getName()
        ];
        $mail =  (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
            ->to($to)
            ->replyTo($replyTo)
            ->subject('[' . $ticket->getEvent()->getName() . '] Your ticket / Lippusi')
            ->addPart((new DataPart($qr['qr'], 'ticket', 'image/png', 'base64'))->asInline())
            ->htmlTemplate('emails/ticket.html.twig')
            ->context([
                'body' => $body,
                'qr' => $qr,
                'links' => $email->getAddLoginLinksToFooter() ? $email->getAddLoginLinksToFooter() : false,
                'img' => $ticket->getEvent()->getPicture(),
                'user_email' => $to
            ]);
        $mailer->send($mail);
        $this->addFlash('success', 'QR-code email sent!');
        return $this->redirect($this->admin->generateUrl('list'));
    }
}
