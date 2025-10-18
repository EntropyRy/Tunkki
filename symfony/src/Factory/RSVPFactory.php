<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Event;
use App\Entity\RSVP;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * RSVPFactory.
 *
 * Creates RSVP entities for event attendance tracking.
 *
 * @extends PersistentObjectFactory<RSVP>
 */
final class RSVPFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RSVP::class;
    }

    protected function defaults(): array
    {
        return [
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'email' => self::faker()->unique()->safeEmail(),
            // 'event' must be provided via forEvent() or explicit with()
            // 'member' is optional (null by default)
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->with(['event' => $event]);
    }
}
