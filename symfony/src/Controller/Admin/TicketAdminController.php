<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Service\Email\EmailService;
use App\Service\QrService;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

    public function sendQrCodeEmailAction(EmailService $emailService, QrService $qrGenerator): RedirectResponse
    {
        $ticket = $this->admin->getSubject();
        $to = null === $ticket->getOwner() ? $ticket->getEmail() : $ticket->getOwner()->getEmail();

        $qrs = [[
            'qr' => $qrGenerator->getQr((string) $ticket->getReferenceNumber()),
            'name' => $ticket->getName() ?? 'Ticket',
        ]];

        $emailService->sendTicketQrEmails(
            $ticket->getEvent(),
            (string) $to,
            $qrs,
            $ticket->getEvent()->getPicture(),
        );
        $this->addFlash('success', 'QR-code email sent!');

        return $this->redirect($this->admin->generateUrl('list'));
    }
}
