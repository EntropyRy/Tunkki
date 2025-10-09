<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Email;
use App\Entity\RSVP;
use App\Entity\Sonata\SonataMediaMedia;
use App\Repository\EmailRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[AsEntityListener(event: Events::postPersist, method: 'sendRSVPMailListener', entity: RSVP::class)]
final readonly class RSVPListener
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailRepository $emailRepository,
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
                'purpose' => 'rsvp',
            ]);
            if ($emailTemplate instanceof Email) {
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

    private function generateMail(string $to, Address|string $replyto, string $subject, string $body, ?bool $links, ?SonataMediaMedia $img): TemplatedEmail
    {
        return new TemplatedEmail()
            ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
            ->to($to)
            ->replyTo($replyto)
            ->subject('[Entropy]'.$subject)
            ->htmlTemplate('emails/email.html.twig')
            ->context(['body' => $body, 'links' => $links, 'img' => $img]);
    }
}
