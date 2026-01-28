<?php

declare(strict_types=1);

namespace App\Factory\Rental\Inventory;

use App\Entity\Rental\Inventory\Accessory;
use App\Entity\Rental\Inventory\AccessoryChoice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * AccessoryFactory.
 *
 * Factory for creating Accessory entities (booking accessories) for tests.
 *
 * @extends PersistentObjectFactory<Accessory>
 */
final class AccessoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Accessory::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'count' => '1',
        ];
    }

    public function forChoice(AccessoryChoice $choice): static
    {
        return $this->with(['name' => $choice]);
    }

    public function withCount(string $count): static
    {
        return $this->with(['count' => $count]);
    }
}
