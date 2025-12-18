<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Email;
use App\Entity\Event;
use App\Enum\EmailPurpose;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * EmailFactory.
 *
 * Lightweight factory for creating Email entities for tests.
 *
 * @extends PersistentObjectFactory<Email>
 */
final class EmailFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Email::class;
    }

    protected function defaults(): array
    {
        return [
            'subject' => self::faker()->sentence(3),
            'body' => '<p>'.self::faker()->paragraph().'</p>',
            'purpose' => EmailPurpose::TIEDOTUS,
            'recipientGroups' => [],
            'addLoginLinksToFooter' => true,
            'replyTo' => 'hallitus@entropy.fi',
        ];
    }

    // State methods for common purposes

    public function aktiivit(): static
    {
        return $this->with([
            'purpose' => EmailPurpose::AKTIIVIT,
        ]);
    }

    public function tiedotus(): static
    {
        return $this->with([
            'purpose' => EmailPurpose::TIEDOTUS,
        ]);
    }

    public function rsvp(): static
    {
        return $this->with([
            'purpose' => EmailPurpose::RSVP,
        ]);
    }

    public function ticket(): static
    {
        return $this->with([
            'purpose' => EmailPurpose::TICKET,
        ]);
    }

    public function ticketQr(): static
    {
        return $this->with([
            'purpose' => EmailPurpose::TICKET_QR,
        ]);
    }

    public function selectedArtist(): static
    {
        return $this->with([
            'purpose' => EmailPurpose::SELECTED_ARTIST,
        ]);
    }

    public function forEvent(Event|Proxy $event): static
    {
        return $this->with([
            'event' => $event,
        ]);
    }

    public function withRecipientGroups(array $purposes): static
    {
        return $this->with([
            'recipientGroups' => $purposes,
        ]);
    }
}
