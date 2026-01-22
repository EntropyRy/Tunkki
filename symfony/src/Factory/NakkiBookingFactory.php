<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\NakkiBooking;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<NakkiBooking>
 */
final class NakkiBookingFactory extends PersistentObjectFactory
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
            'nakkikone' => NakkikoneFactory::new(),
            'startAt' => $start,
            'endAt' => $end,
            'member' => null,
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (NakkiBooking $booking): void {
            $nakki = $booking->getNakki();
            if ($nakki->getNakkikone() !== $booking->getNakkikone()) {
                $booking->setNakkikone($nakki->getNakkikone());
            }
        });
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
