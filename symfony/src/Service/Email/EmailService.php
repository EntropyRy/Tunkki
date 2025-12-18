<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\DTO\EmailRecipient;
use App\DTO\EmailSendResult;
use App\Entity\Email;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Sonata\SonataMediaMedia;
use App\Enum\EmailPurpose;
use App\Repository\EmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Centralized email service for all email sending operations.
 *
 * Used by:
 * 1. EmailAdminController::sendAction() - Bulk admin sends with progress
 * 2. RSVPListener::sendRSVPMailListener() - Auto RSVP confirmation
 * 3. StripeEventSubscriber::sendTicketQrEmail() - Auto ticket QR codes
 * 4. BookingAdminSubscriber::sendEmailNotification() - Admin notifications
 * 5. MemberAdminController::activememberinfoAction() - Active member info package
 * 6. TicketAdminController::sendQrCodeEmailAction() - Manual QR resend
 *
 * NOT used by (keep as-is):
 * - ProfileController::newMember() - Uses EmailVerifier for verification emails
 * - ResetPasswordController - Uses direct mailer for security-critical password reset
 * - EmailVerifier - Uses signed URLs for email verification
 */
class EmailService
{
    public function __construct(
        private readonly EmailRepository $emailRepository,
        private readonly RecipientResolver $recipientResolver,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Send email to recipients resolved from Email entity's purpose + recipientGroups.
     *
     * @param callable|null $progressCallback Called after each email: fn(int $current, int $total) => void
     */
    public function send(
        Email $email,
        ?callable $progressCallback = null,
        ?Member $sentBy = null,
    ): EmailSendResult {
        $purpose = $email->getPurpose();
        if (!$purpose instanceof EmailPurpose) {
            throw new \RuntimeException('Email entity must have a purpose set');
        }

        $recipientGroups = $email->getRecipientGroups();
        $allPurposes = [$purpose, ...$recipientGroups];

        // Resolve recipients (with deduplication for multiple purposes)
        $recipients = 1 === \count($allPurposes)
            ? $this->recipientResolver->resolve($purpose, $email->getEvent())
            : $this->recipientResolver->resolveMultiple($allPurposes, $email->getEvent());

        // Send emails
        $sentCount = 0;
        $failedRecipients = [];
        $totalRecipients = \count($recipients);

        foreach ($recipients as $index => $recipient) {
            try {
                $message = $this->createEmailMessage(
                    $email,
                    $recipient
                );

                $this->mailer->send($message);
                ++$sentCount;
            } catch (\Exception) {
                $failedRecipients[] = $recipient->email;
            }

            if ($progressCallback) {
                $progressCallback($index + 1, $totalRecipients);
            }
        }

        // Update email template metadata if sent by someone
        if ($sentBy && $sentCount > 0) {
            $email->setSentAt(new \DateTimeImmutable());
            $email->setSentBy($sentBy);
            $this->entityManager->flush();
        }

        return new EmailSendResult(
            totalSent: $sentCount,
            totalRecipients: $totalRecipients,
            purposes: $allPurposes,
            failedRecipients: $failedRecipients,
            sentAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Send a single email to a specific recipient (for automated/transactional emails).
     */
    public function sendToRecipient(
        EmailPurpose $purpose,
        string $recipientEmail,
        ?Event $event = null,
        string $locale = 'fi',
        ?array $extraContext = null,
    ): void {
        $emailTemplate = $this->emailRepository->findOneBy([
            'purpose' => $purpose->value,
            'event' => $event,
        ]);

        if (!$emailTemplate instanceof Email) {
            throw new \RuntimeException(\sprintf('Email template not found for purpose "%s"', $purpose->value));
        }

        $recipient = new EmailRecipient($recipientEmail, $locale);
        $message = $this->createEmailMessage($emailTemplate, $recipient, $extraContext);

        $this->mailer->send($message);
    }

    /**
     * Send ticket QR code emails (special case with attachments).
     *
     * Used by StripeEventSubscriber and TicketAdminController.
     * Sends one email per QR code (one per ticket).
     *
     * @param array<array{name: string, qr: string}> $qrs Array of QR data (base64 encoded images)
     */
    public function sendTicketQrEmails(
        Event $event,
        string $recipientEmail,
        array $qrs,
        ?SonataMediaMedia $img = null,
    ): void {
        $emailTemplate = $this->emailRepository->findOneBy([
            'purpose' => EmailPurpose::TICKET_QR->value,
            'event' => $event,
        ]);

        $replyTo = $emailTemplate?->getReplyTo() ?? 'hallitus@entropy.fi';
        $body = $emailTemplate?->getBody() ?? '';
        $links = $emailTemplate?->getAddLoginLinksToFooter() ?? true;

        foreach ($qrs as $index => $qr) {
            // Add ticket number to subject for multiple tickets
            $subject = $index > 0
                ? '[ENTROPY] '.$qr['name'].' ('.($index + 1).')'
                : '[ENTROPY] '.$qr['name'];

            $mail = new TemplatedEmail()
                ->to($recipientEmail)
                ->replyTo($replyTo)
                ->subject($subject)
                ->addPart(
                    new DataPart(
                        $qr['qr'],
                        'ticket',
                        'image/png',
                        'base64',
                    )->asInline(),
                )
                ->htmlTemplate('emails/ticket.html.twig')
                ->context([
                    'body' => $body,
                    'qr' => $qr,
                    'links' => $links,
                    'img' => $img,
                    'user_email' => $recipientEmail,
                ]);

            $this->mailer->send($mail);
        }
    }

    /**
     * Create a Symfony TemplatedEmail from Email entity and recipient.
     */
    private function createEmailMessage(
        Email $emailTemplate,
        EmailRecipient $recipient,
        ?array $extraContext = null,
    ): TemplatedEmail {
        $replyTo = $emailTemplate->getReplyTo() ?: 'hallitus@entropy.fi';

        $context = [
            'body' => $emailTemplate->getBody(),
            'links' => $emailTemplate->getAddLoginLinksToFooter(),
            'img' => $emailTemplate->getEvent()?->getPicture(),
            'locale' => $recipient->locale,
        ];

        if ($extraContext) {
            $context = array_merge($context, $extraContext);
        }

        $email = new TemplatedEmail()
            ->to($recipient->email)
            ->replyTo($replyTo)
            ->subject('[Entropy] '.$emailTemplate->getSubject())
            ->htmlTemplate('emails/email.html.twig')
            ->context($context);

        // Set FROM with localized sender name for ACTIVE_MEMBER_INFO_PACKAGE
        // All other email types use global mailer config (webmaster@entropy.fi)
        if (EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE === $emailTemplate->getPurpose()) {
            $fromName = 'en' === $recipient->locale ? 'Entropy Board' : 'Entropyn Hallitus';
            $email->from(new Address('hallitus@entropy.fi', $fromName));
        }

        return $email;
    }
}
