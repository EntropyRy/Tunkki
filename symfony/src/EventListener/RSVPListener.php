<?php

namespace App\EventListener;

use App\Entity\RSVP;
use App\Repository\EmailRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Doctrine\ORM\Events;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsEntityListener(event: Events::postPersist, method: 'sendRSVPMailListener', entity: RSVP::class)]
final readonly class RSVPListener
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailRepository $emailRepository
    ) {
    }
    public function sendRSVPMailListener(RSVP $rsvp): void
    {
        // Send an email to the user who RSVP'd
        $event = $rsvp->getEvent();
        $userMail = $rsvp->getAvailableEmail();
        if ($event->getRsvpSystemEnabled() && $event->isSendRsvpEmail() && $userMail) {
            $emailTemplate = $this->emailRepository->findOneBy([
                'event' => $event,
                'purpose' => 'rsvp'
            ]);
            if ($emailTemplate) {
                $email = $this->generateMail(
                    $userMail,
                    $emailTemplate->getReplyTo() ?: 'hallitus@entropy.fi',
                    $emailTemplate->getSubject(),
                    $emailTemplate->getBody(),
                    $emailTemplate->getAddLoginLinksToFooter(),
                    $event->getPicture()
                );
                $this->mailer->send($email);
            }
        }
    }
    private function generateMail($to, $replyto, $subject, $body, $links, $img): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
            ->to($to)
            ->replyTo($replyto)
            ->subject($subject)
            ->htmlTemplate('emails/email.html.twig')
            ->context(['body' => $body, 'links' => $links, 'img' => $img]);
    }
}
