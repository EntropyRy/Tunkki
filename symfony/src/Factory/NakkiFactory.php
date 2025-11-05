<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Nakki;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Nakki>
 */
final class NakkiFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Nakki::class;
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();
        $start = $now->modify('+1 day')->setTime(10, 0);
        $end = $start->modify('+8 hours');

        return [
            'event' => EventFactory::new(),
            'definition' => NakkiDefinitionFactory::new(),
            'startAt' => $start,
            'endAt' => $end,
            'nakkiInterval' => new \DateInterval('PT1H'),
            'responsible' => null,
            'mattermostChannel' => null,
            'disableBookings' => false,
        ];
    }

    /**
     * Set the nakki as having bookings disabled.
     */
    public function disabled(): static
    {
        return $this->with(['disableBookings' => true]);
    }

    /**
     * Set the nakki as having bookings enabled.
     */
    public function enabled(): static
    {
        return $this->with(['disableBookings' => false]);
    }

    /**
     * Set a specific time interval for bookings.
     */
    public function withInterval(int $hours): static
    {
        return $this->with(['nakkiInterval' => new \DateInterval("PT{$hours}H")]);
    }
}
