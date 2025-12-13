<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Ticket;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * TicketFactory.
 *
 * Factory for creating Ticket entities with sensible defaults.
 *
 * Goals:
 *  - Provide defaults for common ticket scenarios
 *  - Support various ticket statuses (available, reserved, paid, paid_with_bus)
 *  - Fluent helpers to attach Event and Member
 *
 * Example:
 *   $ticket = TicketFactory::new()
 *       ->forEvent($event)
 *       ->ownedBy($member)
 *       ->paid()
 *       ->create();
 *
 * @extends PersistentObjectFactory<Ticket>
 */
final class TicketFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Ticket::class;
    }

    /**
     * Default attribute set.
     */
    protected function defaults(): array
    {
        return [
            'price' => self::faker()->numberBetween(500, 5000), // Price in cents
            'status' => 'available',
            'referenceNumber' => self::faker()->unique()->numberBetween(100000, 999999),
            'given' => false,
            'email' => self::faker()->optional()->safeEmail(),
            'name' => self::faker()->optional()->words(2, true),
            'stripeProductId' => 'prod_test_'.bin2hex(random_bytes(8)),
        ];
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this;
        // No special initialization needed - Ticket constructor handles updatedAt
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Attach to a specific Event.
     *
     * @param Event|Proxy<Event> $event
     */
    public function forEvent(Event|Proxy $event): static
    {
        return $this->with(['event' => $event]);
    }

    /**
     * Set owner to a specific Member.
     *
     * @param Member|Proxy<Member>|null $owner
     */
    public function ownedBy(Member|Proxy|null $owner): static
    {
        return $this->with(['owner' => $owner]);
    }

    /**
     * Mark ticket as available (default state).
     */
    public function available(): static
    {
        return $this->with(['status' => 'available', 'owner' => null]);
    }

    /**
     * Mark ticket as reserved.
     */
    public function reserved(): static
    {
        return $this->with(['status' => 'reserved']);
    }

    /**
     * Mark ticket as paid.
     */
    public function paid(): static
    {
        return $this->with(['status' => 'paid']);
    }

    /**
     * Mark ticket as paid with bus.
     */
    public function paidWithBus(): static
    {
        return $this->with(['status' => 'paid_with_bus']);
    }

    /**
     * Mark ticket as given (handed out at door).
     */
    public function given(): static
    {
        return $this->with(['given' => true]);
    }

    /**
     * Set a specific price in cents.
     */
    public function withPrice(int $priceCents): static
    {
        return $this->with(['price' => $priceCents]);
    }

    /**
     * Set a specific reference number.
     */
    public function withReferenceNumber(int $reference): static
    {
        return $this->with(['referenceNumber' => $reference]);
    }

    /**
     * Set ticket name.
     */
    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Set email.
     */
    public function withEmail(string $email): static
    {
        return $this->with(['email' => $email]);
    }
}
