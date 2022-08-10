<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
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
        return $this->renderWithExtraParams('emails/base.html.twig', ['email' => $email, 'admin' => $admin]);
    }
    public function sendAction()
    {
        $email = $this->admin->getSubject();
        $purpose = $email->getPurpose();
        $subject = $email->getSubject();
        $event = $email->getEvent();
        $body = $email->getBody();
        $count = 0;
        $replyto = $email->getReplyTo()?$email->getReplyTo():'hallitus@entropy.fi';
        if ($subject && $body && $event) {
            if ($purpose == 'rsvp'){
                $rsvps = $event->getRSVPs();
                if(count($rsvps) > 0){
                    foreach ($rsvps as $rsvp) {
                        $to = $rsvp->getAvailableEmail();
                        $message = $this->generateMail($to, $replyto, $subject, $body);
                        $this->get('symfony.mailer')->send($message);
                        $count += 1;
                    }
                }
                
            } elseif ($purpose == 'ticket'){
                $tickets = $event->getTickets();
                foreach ($tickets as $ticket) {
                    if ($ticket->getStatus() == 'paid' || $ticket->getStatus() == 'reserved'){
                        $to = $ticket->getOwnerEmail();
                        if($to) {
                            $message = $this->generateMail($to, $replyto, $subject, $body);
                            $this->get('symfony.mailer')->send($message);
                            $count += 1;
                        }
                    }
                }
            } elseif ($purpose == 'nakkikone'){
                $nakkis = $event->getNakkiBookings();
                foreach ($nakkis as $nakki) {
                    $to = $nakki->getMemberEmail();
                    if($to){
                        $message = $this->generateMail($to, $replyto, $subject, $body);
                        $this->get('symfony.mailer')->send($message);
                        $count += 1;
                    }
                }
            } 

            $this->addFlash('sonata_flash_success', sprintf('%s %s info packages sent.', $count, $purpose));
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
    }
    private function generateMail($to, $replyto, $subject, $body): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy Ry'))
            ->to($to)
            ->replyTo($replyto)
            ->subject($subject)
            ->htmlTemplate('emails/rsvp.html.twig')
            ->context(['body' => $body ])
        ;
    }
}
