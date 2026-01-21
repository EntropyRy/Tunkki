<?php

declare(strict_types=1);

namespace App\Factory\Rental\Inventory;

use App\Entity\Rental\Inventory\Item;
use App\Entity\Rental\Inventory\WhoCanRentChoice;
use App\Entity\Sonata\SonataClassificationCategory as Category;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * ItemFactory.
 *
 * Lightweight factory for creating Item entities (rental equipment) for tests.
 *
 * Goals:
 *  - Provide sensible defaults for a rentable item with basic info.
 *  - Offer expressive states for common permutations (needsFixing, forSale, spareParts).
 *  - Support cloning tests by providing all required non-null properties.
 *
 * Example:
 *   $item = ItemFactory::createOne();
 *   $item = ItemFactory::new()->needsFixing()->create();
 *   $item = ItemFactory::new()->withRent('25.00')->create();
 *   $item = ItemFactory::new()->forSale()->create();
 *
 * NOTE: The clone action in ItemAdminController requires non-null values for
 * manufacturer, model, description, placeinstorage, and rentNotice. Use the
 * cloneable() state or provide these values when testing clone functionality.
 *
 * @extends PersistentObjectFactory<Item>
 */
final class ItemFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Item::class;
    }

    /**
     * Default attribute set.
     *
     * Provides sensible defaults for a basic rentable item:
     *  - Unique name with faker words
     *  - Standard rental price (€10.00)
     *  - Not broken, not for sale, can be rented
     *
     * NOTE: Properties with setters that don't accept null (manufacturer, model,
     * description, placeinstorage, rentNotice) are omitted from defaults.
     * Use cloneable() state or withX() methods to set them when needed.
     */
    protected function defaults(): callable
    {
        return static function (): array {
            $nameBase = self::faker()->words(2, true);

            return [
                'name' => ucfirst($nameBase).' '.self::faker()->randomNumber(4),
                'rent' => '10.00',
                'needsFixing' => false,
                'toSpareParts' => false,
                'cannotBeRented' => false,
                'forSale' => false,
            ];
        };
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Item $item): void {
            // Lifecycle callbacks handle createdAt/updatedAt
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Create an item with all properties populated (required for clone action).
     *
     * The ItemAdminController::cloneAction() copies properties using setters
     * that don't accept null, so we need non-null values for cloning to work.
     */
    public function cloneable(): static
    {
        return $this->with([
            'manufacturer' => self::faker()->company(),
            'model' => self::faker()->word().' '.self::faker()->randomNumber(3),
            'placeinstorage' => 'Shelf '.strtoupper(self::faker()->randomLetter()).self::faker()->randomDigit(),
            'description' => self::faker()->sentence(10),
            'rentNotice' => self::faker()->sentence(5),
            'serialnumber' => strtoupper(self::faker()->bothify('??-####-????')),
        ]);
    }

    /**
     * Mark item as needing repair.
     */
    public function needsFixing(): static
    {
        return $this->with([
            'needsFixing' => true,
            'cannotBeRented' => true,
        ]);
    }

    /**
     * Mark item as available for sale.
     */
    public function forSale(): static
    {
        return $this->with([
            'forSale' => true,
        ]);
    }

    /**
     * Mark item as spare parts only (not rentable).
     */
    public function spareParts(): static
    {
        return $this->with([
            'toSpareParts' => true,
            'cannotBeRented' => true,
        ]);
    }

    /**
     * Mark item as not rentable.
     */
    public function cannotBeRented(): static
    {
        return $this->with([
            'cannotBeRented' => true,
        ]);
    }

    /**
     * Item in good condition, ready for rental.
     */
    public function rentable(): static
    {
        return $this->with([
            'needsFixing' => false,
            'toSpareParts' => false,
            'cannotBeRented' => false,
        ]);
    }

    /* -----------------------------------------------------------------
     * Property Setters
     * ----------------------------------------------------------------- */

    /**
     * Set rental price.
     */
    public function withRent(string $rent): static
    {
        return $this->with(['rent' => $rent]);
    }

    /**
     * Set item name.
     */
    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Set manufacturer.
     */
    public function withManufacturer(string $manufacturer): static
    {
        return $this->with(['manufacturer' => $manufacturer]);
    }

    /**
     * Set model.
     */
    public function withModel(string $model): static
    {
        return $this->with(['model' => $model]);
    }

    /**
     * Set storage location.
     */
    public function withPlaceInStorage(string $place): static
    {
        return $this->with(['placeinstorage' => $place]);
    }

    /**
     * Set description.
     */
    public function withDescription(string $description): static
    {
        return $this->with(['description' => $description]);
    }

    /**
     * Set rent notice.
     */
    public function withRentNotice(string $notice): static
    {
        return $this->with(['rentNotice' => $notice]);
    }

    /**
     * Set compensation price.
     */
    public function withCompensationPrice(string $price): static
    {
        return $this->with(['compensationPrice' => $price]);
    }

    /* -----------------------------------------------------------------
     * Relations
     * ----------------------------------------------------------------- */

    /**
     * Set category.
     */
    public function withCategory(Category $category): static
    {
        return $this->with(['category' => $category]);
    }

    /**
     * Set creator user.
     */
    public function createdBy(User $user): static
    {
        return $this->with(['creator' => $user]);
    }

    /**
     * Set modifier user.
     */
    public function modifiedBy(User $user): static
    {
        return $this->with(['modifier' => $user]);
    }

    /**
     * Add who can rent choices after instantiation.
     */
    public function withWhoCanRent(WhoCanRentChoice ...$choices): static
    {
        return $this->afterInstantiate(function (Item $item) use ($choices): void {
            foreach ($choices as $choice) {
                $item->addWhoCanRent($choice);
            }
        });
    }
}
