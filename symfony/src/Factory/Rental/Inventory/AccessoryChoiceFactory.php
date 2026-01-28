<?php

declare(strict_types=1);

namespace App\Factory\Rental\Inventory;

use App\Entity\Rental\Inventory\AccessoryChoice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * AccessoryChoiceFactory.
 *
 * Factory for creating AccessoryChoice entities (accessory catalog) for tests.
 *
 * @extends PersistentObjectFactory<AccessoryChoice>
 */
final class AccessoryChoiceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return AccessoryChoice::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'name' => self::faker()->words(2, true).' Cable',
            'compensationPrice' => self::faker()->numberBetween(5, 50),
        ];
    }

    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }

    public function withCompensationPrice(int $price): static
    {
        return $this->with(['compensationPrice' => $price]);
    }
}
