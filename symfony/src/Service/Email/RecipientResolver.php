<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\DTO\EmailRecipient;
use App\Entity\Event;
use App\Entity\Nakkikone;
use App\Enum\EmailPurpose;
use App\Repository\ArtistRepository;
use App\Repository\MemberRepository;

/**
 * Resolves email recipients based on purpose and context.
 *
 * Encapsulates all recipient query logic previously scattered across EmailAdminController.
 */
class RecipientResolver
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly ArtistRepository $artistRepository,
    ) {
    }

    /**
     * Resolve recipients for a single purpose.
     *
     * @return array<EmailRecipient>
     *
     * @throws \InvalidArgumentException if purpose requires Event but none provided
     */
    public function resolve(EmailPurpose $purpose, ?Event $event = null): array
    {
        if ($purpose->requiresEvent() && !$event instanceof Event) {
            throw new \InvalidArgumentException(\sprintf('Purpose "%s" requires an Event context', $purpose->value));
        }

        return match ($purpose) {
            EmailPurpose::RSVP => $this->resolveRsvpRecipients($event),
            EmailPurpose::TICKET => $this->resolveTicketRecipients($event),
            EmailPurpose::NAKKIKONE => $this->resolveNakkiRecipients($event),
            EmailPurpose::ARTIST => $this->resolveAllArtistRecipients($event),
            EmailPurpose::SELECTED_ARTIST => $this->resolveSelectedArtistRecipients($event),
            EmailPurpose::AKTIIVIT => $this->resolveAktiivitRecipients(),
            EmailPurpose::TIEDOTUS => $this->resolveTiedotusRecipients(),
            EmailPurpose::VJ_ROSTER => $this->resolveRosterRecipients('VJ'),
            EmailPurpose::DJ_ROSTER => $this->resolveRosterRecipients('DJ'),
            default => throw new \InvalidArgumentException(\sprintf('Purpose "%s" is not sendable via bulk send', $purpose->value)),
        };
    }

    /**
     * Resolve recipients for multiple purposes with deduplication.
     *
     * @param array<EmailPurpose> $purposes
     *
     * @return array<EmailRecipient>
     */
    public function resolveMultiple(array $purposes, ?Event $event = null): array
    {
        $allRecipients = [];

        foreach ($purposes as $purpose) {
            $recipients = $this->resolve($purpose, $event);
            foreach ($recipients as $recipient) {
                // Deduplicate by email address (case-insensitive)
                $key = $recipient->getDeduplicationKey();
                if (!isset($allRecipients[$key])) {
                    $allRecipients[$key] = $recipient;
                }
            }
        }

        return array_values($allRecipients);
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveRsvpRecipients(Event $event): array
    {
        $recipients = [];
        foreach ($event->getRSVPs() as $rsvp) {
            $email = $rsvp->getAvailableEmail();
            if ($email) {
                $recipients[] = new EmailRecipient($email);
            }
        }

        return $recipients;
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveTicketRecipients(Event $event): array
    {
        $recipients = [];
        $seen = [];

        foreach ($event->getTickets() as $ticket) {
            $status = $ticket->getStatus();
            if (!str_starts_with((string) $status, 'paid') && 'reserved' !== $status) {
                continue;
            }

            $email = $ticket->getOwnerEmail() ?? $ticket->getEmail();
            if ($email && !isset($seen[strtolower($email)])) {
                $recipients[] = new EmailRecipient($email);
                $seen[strtolower($email)] = true;
            }
        }

        return $recipients;
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveNakkiRecipients(Event $event): array
    {
        $recipients = [];
        $nakkikone = $event->getNakkikone();

        if ($nakkikone instanceof Nakkikone) {
            foreach ($nakkikone->getBookings() as $booking) {
                $member = $booking->getMember();
                if ($member) {
                    $recipients[] = new EmailRecipient(
                        $member->getEmail(),
                        $member->getLocale() ?? 'fi',
                        $member->getId()
                    );
                }
            }
        }

        return $recipients;
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveAllArtistRecipients(Event $event): array
    {
        $recipients = [];

        foreach ($event->getEventArtistInfos() as $signup) {
            $artist = $signup->getArtist();
            if (!$artist) {
                continue;
            }

            $member = $artist->getMember();
            if ($member) {
                $recipients[] = new EmailRecipient(
                    $member->getEmail(),
                    $member->getLocale() ?? 'fi',
                    $member->getId()
                );
            }
        }

        return $recipients;
    }

    /**
     * NEW: Resolve only artists with startTime set (scheduled/selected artists).
     *
     * @return array<EmailRecipient>
     */
    private function resolveSelectedArtistRecipients(Event $event): array
    {
        $recipients = [];

        foreach ($event->getEventArtistInfos() as $signup) {
            // Only include if startTime is set (artist has been scheduled)
            if (null === $signup->getStartTime()) {
                continue;
            }

            $artist = $signup->getArtist();
            if (!$artist) {
                continue;
            }

            $member = $artist->getMember();
            if ($member) {
                $recipients[] = new EmailRecipient(
                    $member->getEmail(),
                    $member->getLocale() ?? 'fi',
                    $member->getId()
                );
            }
        }

        return $recipients;
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveAktiivitRecipients(): array
    {
        $recipients = [];

        $members = $this->memberRepository->findBy([
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        foreach ($members as $member) {
            $recipients[] = new EmailRecipient(
                $member->getEmail(),
                $member->getLocale() ?? 'fi',
                $member->getId()
            );
        }

        return $recipients;
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveTiedotusRecipients(): array
    {
        $recipients = [];

        $members = $this->memberRepository->findBy([
            'emailVerified' => true,
            'allowInfoMails' => true,
        ]);

        foreach ($members as $member) {
            $recipients[] = new EmailRecipient(
                $member->getEmail(),
                $member->getLocale() ?? 'fi',
                $member->getId()
            );
        }

        return $recipients;
    }

    /**
     * @return array<EmailRecipient>
     */
    private function resolveRosterRecipients(string $artistType): array
    {
        $recipients = [];

        $artists = $this->artistRepository->findBy([
            'type' => $artistType,
            'copyForArchive' => false,
        ]);

        foreach ($artists as $artist) {
            $member = $artist->getMember();
            if ($member) {
                $recipients[] = new EmailRecipient(
                    $member->getEmail(),
                    $member->getLocale() ?? 'fi',
                    $member->getId()
                );
            }
        }

        return $recipients;
    }
}
