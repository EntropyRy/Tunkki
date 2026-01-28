<?php

declare(strict_types=1);

namespace App\Factory\Rental\Booking;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Entity\Rental\Inventory\Accessory;
use App\Entity\Rental\Inventory\Item;
use App\Entity\Rental\Inventory\Package;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * BookingFactory.
 *
 * Factory for creating Booking entities (rental transactions) for tests.
 *
 * @extends PersistentObjectFactory<Booking>
 */
final class BookingFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Booking::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'name' => 'Booking '.self::faker()->words(2, true),
            'referenceNumber' => 'REF-'.self::faker()->unique()->randomNumber(8),
            'renterHash' => bin2hex(random_bytes(16)),
            'bookingDate' => new \DateTimeImmutable(),
            'numberOfRentDays' => 1,
            'renterConsent' => false,
            'itemsReturned' => false,
            'invoiceSent' => false,
            'paid' => false,
            'cancelled' => false,
        ];
    }

    public function forRenter(Renter $renter): static
    {
        return $this->with(['renter' => $renter]);
    }

    public function withName(string $name): static
    {
        return $this->with(['name' => $name]);
    }

    public function withBookingDate(\DateTimeImmutable $date): static
    {
        return $this->with(['bookingDate' => $date]);
    }

    public function withRetrieval(\DateTimeImmutable $retrieval): static
    {
        return $this->with(['retrieval' => $retrieval]);
    }

    public function withReturning(\DateTimeImmutable $returning): static
    {
        return $this->with(['returning' => $returning]);
    }

    public function withNumberOfRentDays(int $days): static
    {
        return $this->with(['numberOfRentDays' => $days]);
    }

    public function withActualPrice(string $price): static
    {
        return $this->with(['actualPrice' => $price]);
    }

    public function createdBy(User $user): static
    {
        return $this->with(['creator' => $user]);
    }

    public function paid(): static
    {
        return $this->with([
            'paid' => true,
            'paid_date' => new \DateTimeImmutable(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->with(['cancelled' => true]);
    }

    public function itemsReturned(): static
    {
        return $this->with(['itemsReturned' => true]);
    }

    public function invoiceSent(): static
    {
        return $this->with(['invoiceSent' => true]);
    }

    public function withConsent(): static
    {
        return $this->with([
            'renterConsent' => true,
            'renterSignature' => 'data:image/png;base64,testSignatureData',
        ]);
    }

    /**
     * Add items to the booking after instantiation.
     *
     * @param Item[] $items
     */
    public function withItems(array $items): static
    {
        return $this->afterInstantiate(static function (Booking $booking) use ($items): void {
            foreach ($items as $item) {
                $booking->addItem($item);
            }
        });
    }

    /**
     * Add packages to the booking after instantiation.
     *
     * @param Package[] $packages
     */
    public function withPackages(array $packages): static
    {
        return $this->afterInstantiate(static function (Booking $booking) use ($packages): void {
            foreach ($packages as $package) {
                $booking->addPackage($package);
            }
        });
    }

    /**
     * Add accessories to the booking after instantiation.
     *
     * @param Accessory[] $accessories
     */
    public function withAccessories(array $accessories): static
    {
        return $this->afterInstantiate(static function (Booking $booking) use ($accessories): void {
            foreach ($accessories as $accessory) {
                $booking->addAccessory($accessory);
            }
        });
    }
}
