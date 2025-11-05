<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\NakkiBooking;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<NakkiBooking>
 */
final class NakkiBookingFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return NakkiBooking::class;
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();
        $start = $now->modify('+1 day')->setTime(10, 0);
        $end = $start->modify('+1 hour');

        return [
            'nakki' => NakkiFactory::new(),
            'event' => EventFactory::new(),
            'startAt' => $start,
            'endAt' => $end,
            'member' => null,
        ];
    }

    /**
     * Create a booked slot (with member assigned).
     */
    public function booked(): static
    {
        return $this->with(['member' => MemberFactory::new()]);
    }

    /**
     * Create a free slot (no member assigned).
     */
    public function free(): static
    {
        return $this->with(['member' => null]);
    }
}
