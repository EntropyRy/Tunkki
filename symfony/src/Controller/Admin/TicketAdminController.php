<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Ticket;
use App\Repository\EmailRepository;
use App\Repository\TicketRepository;
use App\Service\QrService;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @extends CRUDController<Ticket>
 */
final class TicketAdminController extends CRUDController
{
    public function giveAction(TicketRepository $repo): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        if (null === $ticket->getOwner()) {
            $this->addFlash('warning', 'ticket does not have owner!');
        } else {
            $ticket->setGiven(true);
            $repo->save($ticket, true);
        }

        return $this->redirect($this->admin->generateUrl('list'));
    }

    public function sendQrCodeEmailAction(EmailRepository $emailRepo, MailerInterface $mailer, QrService $qrGenerator): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        $to = null == $ticket->getOwner() ? $ticket->getEmail() : $ticket->getOwner()->getEmail();
        $email = $emailRepo->findOneBy(['purpose' => 'ticket_qr', 'event' => $ticket->getEvent()]);
        $replyTo = 'hallitus@entropy.fi';
        $body = '';
        if (null != $email) {
            $replyTo = $email->getReplyTo() ?? 'hallitus@entropy.fi';
            $body = $email->getBody();
        }
        $qr = [
            'qr' => $qrGenerator->getQr((string) $ticket->getReferenceNumber()),
            'name' => $ticket->getName(),
        ];
        $mail = new TemplatedEmail()
            ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
            ->to($to)
            ->replyTo($replyTo)
            ->subject('['.$ticket->getEvent()->getName().'] Your ticket / Lippusi')
            ->addPart(new DataPart($qr['qr'], 'ticket', 'image/png', 'base64')->asInline())
            ->htmlTemplate('emails/ticket.html.twig')
            ->context([
                'body' => $body,
                'qr' => $qr,
                'links' => $email->getAddLoginLinksToFooter() ?: false,
                'img' => $ticket->getEvent()->getPicture(),
                'user_email' => $to,
            ]);
        $mailer->send($mail);
        $this->addFlash('success', 'QR-code email sent!');

        return $this->redirect($this->admin->generateUrl('list'));
    }
}
