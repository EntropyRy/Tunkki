<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class EmailAdminController extends CRUDController
{
    public function previewAction(): Response
    {
        $email = $this->admin->getSubject();
        $admin = $this->admin;
        //$em = $this->getDoctrine()->getManager();
        //$email = $em->getRepository('App:Email')
        //      ->findOneBy('id' => $object->getId());
        return $this->renderWithExtraParams('emails/email.html.twig', ['body' => $email->getBody(), 'email' => $email, 'admin' => $admin]);
    }
    public function sendAction(MailerInterface $mailer): RedirectResponse
    {
        $email = $this->admin->getSubject();
        $links = $email->getAddLoginLinksToFooter();
        $purpose = $email->getPurpose();
        $subject = $email->getSubject();
        $event = $email->getEvent();
        $body = $email->getBody();
        $count = 0;
        $replyto = $email->getReplyTo() ?: 'hallitus@entropy.fi';
        if ($subject && $body && $event) {
            if ($purpose == 'rsvp') {
                $rsvps = $event->getRSVPs();
                if ((is_countable($rsvps) ? count($rsvps) : 0) > 0) {
                    foreach ($rsvps as $rsvp) {
                        $to = $rsvp->getAvailableEmail();
                        $message = $this->generateMail($to, $replyto, $subject, $body, $links);
                        $mailer->send($message);
                        $count += 1;
                    }
                }
            } elseif ($purpose == 'ticket') {
                $tickets = $event->getTickets();
                foreach ($tickets as $ticket) {
                    if ($ticket->getStatus() == 'paid' || $ticket->getStatus() == 'reserved') {
                        $to = $ticket->getOwnerEmail();
                        if ($to) {
                            $message = $this->generateMail($to, $replyto, $subject, $body, $links);
                            $mailer->send($message);
                            $count += 1;
                        }
                    }
                }
            } elseif ($purpose == 'nakkikone') {
                $nakkis = $event->getNakkiBookings();
                $emails = [];
                foreach ($nakkis as $nakki) {
                    $member = $nakki->getMember();
                    if ($member) {
                        $emails[$member->getId()] = $member->getEmail();
                    }
                }
                foreach ($emails as $to) {
                    if ($to) {
                        $message = $this->generateMail($to, $replyto, $subject, $body, $links);
                        $mailer->send($message);
                        $count += 1;
                    }
                }
            }

            $this->addFlash('sonata_flash_success', sprintf('%s %s info packages sent.', $count, $purpose));
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
    }
    private function generateMail($to, $replyto, $subject, $body, $links): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy Ry'))
            ->to($to)
            ->replyTo($replyto)
            ->subject($subject)
            ->htmlTemplate('emails/email.html.twig')
            ->context(['body' => $body, 'links' => $links])
        ;
    }
}
