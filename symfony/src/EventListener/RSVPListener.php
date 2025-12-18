<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RSVP;
use App\Enum\EmailPurpose;
use App\Service\Email\EmailService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, method: 'sendRSVPMailListener', entity: RSVP::class)]
final readonly class RSVPListener
{
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger,
    ) {
    }

    public function sendRSVPMailListener(RSVP $rsvp): void
    {
        // Send an email to the user who RSVP'd
        $event = $rsvp->getEvent();
        $userMail = $rsvp->getAvailableEmail();

        if ($event->getRsvpSystemEnabled() && $event->isSendRsvpEmail() && $userMail) {
            try {
                $this->emailService->sendToRecipient(
                    EmailPurpose::RSVP,
                    $userMail,
                    $event
                );
            } catch (\Exception $e) {
                // Log error but don't break RSVP creation
                $this->logger->error('Failed to send RSVP email', [
                    'error' => $e->getMessage(),
                    'rsvp_id' => $rsvp->getId(),
                    'event_id' => $event->getId(),
                ]);
            }
        }
    }
}
