<?php

declare(strict_types=1);

namespace App\Factory\Rental\Booking;

use App\Entity\Rental\Booking\Renter;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * RenterFactory.
 *
 * Factory for creating Renter entities (rental customers) for tests.
 *
 * @extends PersistentObjectFactory<Renter>
 */
final class RenterFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Renter::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'name' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'phone' => self::faker()->phoneNumber(),
            'streetadress' => self::faker()->streetAddress(),
            'zipcode' => self::faker()->postcode(),
            'city' => self::faker()->city(),
        ];
    }

    public function withOrganization(string $organization): static
    {
        return $this->with(['organization' => $organization]);
    }

    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }

    public function withEmail(string $email): static
    {
        return $this->with(['email' => $email]);
    }
}
