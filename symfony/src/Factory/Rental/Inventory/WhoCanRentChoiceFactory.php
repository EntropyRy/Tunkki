<?php

declare(strict_types=1);

namespace App\Factory\Rental\Inventory;

use App\Entity\Rental\Inventory\WhoCanRentChoice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * WhoCanRentChoiceFactory.
 *
 * Factory for creating WhoCanRentChoice entities (access privilege levels) for tests.
 *
 * @extends PersistentObjectFactory<WhoCanRentChoice>
 */
final class WhoCanRentChoiceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return WhoCanRentChoice::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'name' => 'Privilege '.self::faker()->unique()->randomNumber(5),
        ];
    }

    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }
}
