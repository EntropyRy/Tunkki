<?php

declare(strict_types=1);

namespace App\Factory\Rental\Inventory;

use App\Entity\Rental\Inventory\Item;
use App\Entity\Rental\Inventory\Package;
use App\Entity\Rental\Inventory\WhoCanRentChoice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * PackageFactory.
 *
 * Factory for creating Package entities (bundled rental items) for tests.
 *
 * @extends PersistentObjectFactory<Package>
 */
final class PackageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Package::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'name' => 'Package '.self::faker()->words(2, true),
            'rent' => '50.00',
            'needsFixing' => false,
        ];
    }

    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }

    public function withRent(string $rent): static
    {
        return $this->with(['rent' => $rent]);
    }

    public function withCompensationPrice(string $price): static
    {
        return $this->with(['compensationPrice' => $price]);
    }

    public function withNotes(string $notes): static
    {
        return $this->with(['notes' => $notes]);
    }

    public function needsFixing(): static
    {
        return $this->with(['needsFixing' => true]);
    }

    /**
     * Add items to the package after instantiation.
     *
     * @param Item[] $items
     */
    public function withItems(array $items): static
    {
        return $this->afterInstantiate(static function (Package $package) use ($items): void {
            foreach ($items as $item) {
                $package->addItem($item);
            }
        });
    }

    /**
     * Add who can rent choices after instantiation.
     */
    public function withWhoCanRent(WhoCanRentChoice ...$choices): static
    {
        return $this->afterInstantiate(static function (Package $package) use ($choices): void {
            foreach ($choices as $choice) {
                $package->addWhoCanRent($choice);
            }
        });
    }
}
