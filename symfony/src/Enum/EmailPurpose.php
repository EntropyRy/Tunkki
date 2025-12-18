<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Email purpose definitions.
 *
 * Distinguishes between:
 * - Template purposes: Tied to specific email templates in the Email entity
 * - Old email lists: Recipient groups (aktiivit, tiedotus) that can be combined with deduplication
 */
enum EmailPurpose: string
{
    // Automatic system emails (tied to Email templates)
    case MEMBER_WELCOME = 'member';
    case ACTIVE_MEMBER_THANK_YOU = 'active_member';
    case TICKET_QR = 'ticket_qr';

    // Event-related (tied to Event and Email templates)
    case RSVP = 'rsvp';
    case TICKET = 'ticket';
    case NAKKIKONE = 'nakkikone';
    case ARTIST = 'artist';
    case SELECTED_ARTIST = 'selected_artist';  // NEW: Only artists with startTime set

    // Old email lists (recipient groups, NOT templates)
    case AKTIIVIT = 'aktiivit';
    case TIEDOTUS = 'tiedotus';

    // Manual admin emails (tied to Email templates)
    case ACTIVE_MEMBER_INFO_PACKAGE = 'active_member_info_package';
    case VJ_ROSTER = 'vj_roster';
    case DJ_ROSTER = 'dj_roster';

    /**
     * Check if this purpose represents an "old email list" (recipient group)
     * rather than a template purpose.
     *
     * Old email lists can be combined in multi-purpose sends with deduplication.
     */
    public function isRecipientGroup(): bool
    {
        return match ($this) {
            self::AKTIIVIT, self::TIEDOTUS => true,
            default => false,
        };
    }

    /**
     * Check if this purpose requires an Event context.
     */
    public function requiresEvent(): bool
    {
        return match ($this) {
            self::RSVP,
            self::TICKET,
            self::NAKKIKONE,
            self::ARTIST,
            self::SELECTED_ARTIST,
            self::TICKET_QR => true,
            default => false,
        };
    }

    /**
     * Get human-readable label for admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::MEMBER_WELCOME => 'Automatic email to new Member on registration',
            self::ACTIVE_MEMBER_THANK_YOU => 'Automatic thank you email to member who requests Active Member status',
            self::ACTIVE_MEMBER_INFO_PACKAGE => 'New Active Member info package (can be sent from the member list)',
            self::VJ_ROSTER => 'Email to All VJs in our roster',
            self::DJ_ROSTER => 'Email to All DJs in our roster',
            self::TIEDOTUS => 'Tiedotus (all members on the site, including active members)',
            self::AKTIIVIT => 'Aktiivit (all active members)',
            self::RSVP => 'To RSVP',
            self::TICKET => 'To reserved and paid tickets holders',
            self::NAKKIKONE => 'To people who have reserved Nakki',
            self::ARTIST => 'To all artists signed up for this event',
            self::SELECTED_ARTIST => 'To selected artists (scheduled with start time)',
            self::TICKET_QR => 'To Stripe tickets buyers. QR code email (automatic)',
        };
    }

    /**
     * Get all singleton purposes (only one email of this type should exist).
     *
     * These are templates for automatic system emails that should only have one definition.
     * VJ_ROSTER and DJ_ROSTER are NOT singletons - they're manual sends and can have multiple emails.
     *
     * @return array<self>
     */
    public static function singletons(): array
    {
        return [
            self::MEMBER_WELCOME,
            self::ACTIVE_MEMBER_THANK_YOU,
            self::ACTIVE_MEMBER_INFO_PACKAGE,
        ];
    }

    /**
     * Check if this purpose can be used in standalone admin (non-event context).
     */
    public function canBeUsedInStandaloneAdmin(): bool
    {
        return !$this->requiresEvent();
    }

    /**
     * Check if this purpose can be used in child admin (event context).
     */
    public function canBeUsedInChildAdmin(): bool
    {
        return $this->requiresEvent() || $this->isRecipientGroup();
    }

    /**
     * Check if this purpose can be used as a recipient group.
     */
    public function canBeRecipientGroup(): bool
    {
        return self::TICKET_QR !== $this
            && ($this->requiresEvent() || $this->isRecipientGroup());
    }
}
