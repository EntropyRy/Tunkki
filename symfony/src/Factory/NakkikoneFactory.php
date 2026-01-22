<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Nakkikone;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Nakkikone>
 */
final class NakkikoneFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Nakkikone::class;
    }

    protected function defaults(): array
    {
        return [
            'event' => EventFactory::new(),
            'enabled' => true,
            'infoFi' => null,
            'infoEn' => null,
            'showLinkInEvent' => false,
            'requireDifferentTimes' => true,
            'requiredForTicketReservation' => false,
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Nakkikone $nakkikone): void {
            $event = $nakkikone->getEvent();
            if (!$event->getNakkikone() instanceof Nakkikone) {
                $event->setNakkikone($nakkikone);
            }
        });
    }

    /**
     * Set the nakkikone as enabled.
     */
    public function enabled(): static
    {
        return $this->with(['enabled' => true]);
    }

    /**
     * Set the nakkikone as disabled.
     */
    public function disabled(): static
    {
        return $this->with(['enabled' => false]);
    }

    /**
     * Require different times for nakki bookings.
     */
    public function withDifferentTimesRequired(): static
    {
        return $this->with(['requireDifferentTimes' => true]);
    }

    /**
     * Allow same times for nakki bookings.
     */
    public function withoutDifferentTimesRequired(): static
    {
        return $this->with(['requireDifferentTimes' => false]);
    }

    /**
     * Require nakki for ticket reservation.
     */
    public function requiredForTickets(): static
    {
        return $this->with(['requiredForTicketReservation' => true]);
    }

    /**
     * Make link visible in event.
     */
    public function withVisibleLink(): static
    {
        return $this->with(['showLinkInEvent' => true]);
    }
}
