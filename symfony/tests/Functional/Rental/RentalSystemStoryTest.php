<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rental;

use App\Factory\Rental\Booking\BookingFactory;
use App\Factory\Rental\Booking\RenterFactory;
use App\Factory\Rental\Inventory\AccessoryChoiceFactory;
use App\Factory\Rental\Inventory\AccessoryFactory;
use App\Factory\Rental\Inventory\ItemFactory;
use App\Factory\Rental\Inventory\PackageFactory;
use App\Factory\Rental\Inventory\WhoCanRentChoiceFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Rental System Story Test.
 *
 * Comprehensive functional tests covering the full rental workflow:
 * - Inventory management (Items, Packages, Accessories)
 * - Booking creation and management
 * - Admin form interactions using custom form types (ItemsType, PackagesType)
 * - Price calculations and snapshots
 */
#[Group('rental')]
#[Group('admin')]
final class RentalSystemStoryTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    /* -------------------------------------------------------------------------
     * Story Part 1: Inventory Creation Tests
     * ------------------------------------------------------------------------- */

    public function testCreateItemUsingFactory(): void
    {
        // Arrange & Act: Create a rentable item using factory
        $item = ItemFactory::new()
            ->withName('Test Microphone SM58')
            ->withRent('25.00')
            ->withCompensationPrice('150.00')
            ->withManufacturer('Shure')
            ->withModel('SM58')
            ->rentable()
            ->create();

        // Assert: Item was created with correct properties
        self::assertNotNull($item->getId());
        self::assertSame('Test Microphone SM58', $item->getName());
        self::assertSame('25.00', $item->getRent());
        self::assertSame('150.00', $item->getCompensationPrice());
        self::assertSame('Shure', $item->getManufacturer());
        self::assertSame('SM58', $item->getModel());
        self::assertFalse($item->getNeedsFixing());
        self::assertFalse($item->getCannotBeRented());
    }

    public function testCreatePackageWithItemsUsingFactory(): void
    {
        // Arrange: Create individual items first
        $item1 = ItemFactory::new()
            ->withName('Package Item A - Mixer')
            ->withRent('30.00')
            ->create();

        $item2 = ItemFactory::new()
            ->withName('Package Item B - Speakers')
            ->withRent('40.00')
            ->create();

        // Act: Create a package containing these items
        $package = PackageFactory::new()
            ->withName('DJ Package Complete')
            ->withRent('100.00')
            ->withCompensationPrice('500.00')
            ->withItems([$item1, $item2])
            ->create();

        // Assert: Package was created with items
        self::assertNotNull($package->getId());
        self::assertSame('DJ Package Complete', $package->getName());
        self::assertSame('100.00', $package->getRent());
        self::assertCount(2, $package->getItems());
        self::assertTrue($package->getItems()->contains($item1));
        self::assertTrue($package->getItems()->contains($item2));

        // Assert: Items are now associated with the package
        $this->em()->refresh($item1);
        $this->em()->refresh($item2);
        self::assertTrue($item1->getPackages()->contains($package));
        self::assertTrue($item2->getPackages()->contains($package));
    }

    public function testCreateAccessoryChoiceAndAccessory(): void
    {
        // Arrange & Act: Create an accessory choice (catalog entry)
        $choice = AccessoryChoiceFactory::new()
            ->withName('XLR Cable 10m')
            ->withCompensationPrice(15)
            ->create();

        // Assert: Choice was created
        self::assertNotNull($choice->getId());
        self::assertSame('XLR Cable 10m', $choice->getName());
        self::assertSame(15, $choice->getCompensationPrice());

        // Act: Create an accessory using this choice
        $accessory = AccessoryFactory::new()
            ->forChoice($choice)
            ->withCount('3')
            ->create();

        // Assert: Accessory was created with the choice
        self::assertNotNull($accessory->getId());
        self::assertSame('3', $accessory->getCount());
        self::assertSame($choice, $accessory->getName());
    }

    public function testCreateWhoCanRentChoice(): void
    {
        // Arrange & Act: Create access privilege level
        $privilege = WhoCanRentChoiceFactory::new()
            ->withName('Test Organization')
            ->create();

        // Assert: Privilege was created
        self::assertNotNull($privilege->getId());
        self::assertSame('Test Organization', $privilege->getName());
    }

    /* -------------------------------------------------------------------------
     * Story Part 2: Booking Creation Tests
     * ------------------------------------------------------------------------- */

    public function testCreateRenterUsingFactory(): void
    {
        // Arrange & Act: Create a renter
        $renter = RenterFactory::new()
            ->withName('John Doe')
            ->withEmail('john.doe@example.test')
            ->withOrganization('Acme Corp')
            ->create();

        // Assert: Renter was created
        self::assertNotNull($renter->getId());
        self::assertSame('John Doe', $renter->getName());
        self::assertSame('john.doe@example.test', $renter->getEmail());
        self::assertSame('Acme Corp', $renter->getOrganization());
        self::assertSame('John Doe / Acme Corp', (string) $renter);
    }

    public function testCreateBasicBookingUsingFactory(): void
    {
        // Arrange: Create a renter first
        $renter = RenterFactory::new()
            ->withName('Booking Test Renter')
            ->create();

        $bookingDate = new \DateTimeImmutable('2025-06-15');

        // Act: Create a booking
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Summer Festival Rental')
            ->withBookingDate($bookingDate)
            ->withNumberOfRentDays(3)
            ->create();

        // Assert: Booking was created
        self::assertNotNull($booking->getId());
        self::assertSame('Summer Festival Rental', $booking->getName());
        self::assertSame($renter, $booking->getRenter());
        self::assertSame(3, $booking->getNumberOfRentDays());
        self::assertNotEmpty($booking->getRenterHash());
        self::assertNotEmpty($booking->getReferenceNumber());
    }

    public function testCreateBookingWithItemsAndPackages(): void
    {
        // Arrange: Create inventory
        $renter = RenterFactory::new()->create();

        $item1 = ItemFactory::new()
            ->withName('Standalone Item 1')
            ->withRent('20.00')
            ->create();

        $item2 = ItemFactory::new()
            ->withName('Standalone Item 2')
            ->withRent('30.00')
            ->create();

        $packageItem = ItemFactory::new()
            ->withName('Item in Package')
            ->withRent('15.00')
            ->create();

        $package = PackageFactory::new()
            ->withName('Test Package')
            ->withRent('60.00')
            ->withItems([$packageItem])
            ->create();

        // Act: Create booking with items and packages
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Full Rental Booking')
            ->withItems([$item1, $item2])
            ->withPackages([$package])
            ->create();

        // Assert: Booking contains items and packages
        self::assertCount(2, $booking->getItems());
        self::assertTrue($booking->getItems()->contains($item1));
        self::assertTrue($booking->getItems()->contains($item2));

        self::assertCount(1, $booking->getPackages());
        self::assertTrue($booking->getPackages()->contains($package));

        // Assert: Snapshots are created for price preservation
        self::assertCount(2, $booking->getItemSnapshots());
        self::assertCount(1, $booking->getPackageSnapshots());
    }

    public function testCreateBookingWithAccessories(): void
    {
        // Arrange: Create accessory choice and accessories
        $choice1 = AccessoryChoiceFactory::new()
            ->withName('Power Cable')
            ->withCompensationPrice(10)
            ->create();

        $choice2 = AccessoryChoiceFactory::new()
            ->withName('Mic Stand')
            ->withCompensationPrice(25)
            ->create();

        $accessory1 = AccessoryFactory::new()
            ->forChoice($choice1)
            ->withCount('2')
            ->create();

        $accessory2 = AccessoryFactory::new()
            ->forChoice($choice2)
            ->withCount('1')
            ->create();

        $renter = RenterFactory::new()->create();

        // Act: Create booking with accessories
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Booking with Accessories')
            ->withAccessories([$accessory1, $accessory2])
            ->create();

        // Assert: Booking contains accessories
        self::assertCount(2, $booking->getAccessories());
        self::assertTrue($booking->getAccessories()->contains($accessory1));
        self::assertTrue($booking->getAccessories()->contains($accessory2));
    }

    /* -------------------------------------------------------------------------
     * Story Part 3: Price Calculation Tests
     * ------------------------------------------------------------------------- */

    public function testBookingCalculatedTotalPriceFromItems(): void
    {
        // Arrange: Create items with known prices
        $renter = RenterFactory::new()->create();

        $item1 = ItemFactory::new()
            ->withName('Price Test Item 1')
            ->withRent('50.00')
            ->create();

        $item2 = ItemFactory::new()
            ->withName('Price Test Item 2')
            ->withRent('75.00')
            ->create();

        // Act: Create booking with items
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Price Calculation Test')
            ->withItems([$item1, $item2])
            ->create();

        // Assert: Calculated total is sum of item rents
        $calculatedTotal = $booking->getCalculatedTotalPrice();
        self::assertSame(125, $calculatedTotal); // 50 + 75 = 125
    }

    public function testBookingCalculatedTotalPriceFromPackages(): void
    {
        // Arrange: Create package with known price
        $renter = RenterFactory::new()->create();

        $packageItem = ItemFactory::new()
            ->withName('Package Internal Item')
            ->withRent('30.00')
            ->create();

        $package = PackageFactory::new()
            ->withName('Price Test Package')
            ->withRent('80.00')
            ->withItems([$packageItem])
            ->create();

        // Act: Create booking with package
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Package Price Test')
            ->withPackages([$package])
            ->create();

        // Assert: Calculated total uses package rent (not sum of items)
        $calculatedTotal = $booking->getCalculatedTotalPrice();
        self::assertSame(80, $calculatedTotal);
    }

    public function testBookingSnapshotsPreservePriceAtBookingTime(): void
    {
        // Arrange: Create item and booking
        $renter = RenterFactory::new()->create();

        $item = ItemFactory::new()
            ->withName('Snapshot Price Test')
            ->withRent('100.00')
            ->withCompensationPrice('300.00')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Snapshot Test Booking')
            ->withItems([$item])
            ->create();

        // Get the initial calculated price
        $initialPrice = $booking->getCalculatedTotalPrice();
        self::assertSame(100, $initialPrice);

        // Act: Change the item price after booking was created
        $item->setRent('200.00');
        $this->em()->persist($item);
        $this->em()->flush();

        // Refresh booking to ensure we have fresh data
        $this->em()->refresh($booking);

        // Assert: Booking still calculates using snapshot price (100), not new price (200)
        // The snapshot is created when item is added, so it preserves the original price
        $snapshotPrice = $booking->getCalculatedTotalPrice();
        self::assertSame(100, $snapshotPrice, 'Snapshot should preserve original price');
    }

    /* -------------------------------------------------------------------------
     * Story Part 4: Booking Status Tests
     * ------------------------------------------------------------------------- */

    public function testBookingStatusTransitions(): void
    {
        // Arrange: Create a booking
        $renter = RenterFactory::new()->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Status Test Booking')
            ->create();

        // Assert: Initial status
        self::assertFalse($booking->getRenterConsent());
        self::assertFalse($booking->getItemsReturned());
        self::assertFalse($booking->getInvoiceSent());
        self::assertFalse($booking->getPaid());
        self::assertFalse($booking->getCancelled());

        // Act: Progress through booking lifecycle
        $booking->setRenterConsent(true);
        $booking->setRenterSignature('data:image/png;base64,testSignature');
        $this->em()->flush();

        self::assertTrue($booking->getRenterConsent());
        self::assertNotNull($booking->getRenterSignature());

        // Act: Mark items returned
        $booking->setItemsReturned(true);
        $this->em()->flush();

        self::assertTrue($booking->getItemsReturned());

        // Act: Mark as paid
        $booking->setPaid(true);
        $this->em()->flush();

        self::assertTrue($booking->getPaid());
        self::assertNotNull($booking->getPaidDate());
    }

    public function testBookingWithRetrievalAndReturningDates(): void
    {
        // Arrange
        $renter = RenterFactory::new()->create();

        $retrieval = new \DateTimeImmutable('2025-07-01 10:00:00');
        $returning = new \DateTimeImmutable('2025-07-03 18:00:00');

        // Act
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Date Test Booking')
            ->withRetrieval($retrieval)
            ->withReturning($returning)
            ->create();

        // Assert
        self::assertEquals($retrieval, $booking->getRetrieval());
        self::assertEquals($returning, $booking->getReturning());
    }

    /* -------------------------------------------------------------------------
     * Story Part 5: Admin List Page Access Tests
     * ------------------------------------------------------------------------- */

    public function testBookingAdminListPageAccessibleToAdmin(): void
    {
        // Arrange: Login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Request the booking list page
        $this->client->request('GET', '/admin/booking/list');

        // Assert: Page is accessible
        self::assertResponseIsSuccessful();
    }

    public function testBookingAdminListPageDeniedToAnonymous(): void
    {
        // Arrange: Reset auth to ensure anonymous
        $this->resetAuthSession();

        // Act: Request booking list without authentication
        $this->client->request('GET', '/admin/booking/list');

        // Assert: Redirected to login
        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '#/login(/|$)#',
            $response->headers->get('Location') ?? '',
        );
    }

    public function testItemAdminListPageAccessibleToAdmin(): void
    {
        // Arrange: Login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Request the item list page
        $this->client->request('GET', '/admin/item/list');

        // Assert: Page is accessible
        self::assertResponseIsSuccessful();
    }

    public function testPackageAdminListPageAccessibleToAdmin(): void
    {
        // Arrange: Login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Request the package list page
        $this->client->request('GET', '/admin/package/list');

        // Assert: Page is accessible
        self::assertResponseIsSuccessful();
    }

    /* -------------------------------------------------------------------------
     * Story Part 6: Booking Edit Form Tests
     * ------------------------------------------------------------------------- */

    public function testBookingShowPageRendersSuccessfully(): void
    {
        // Arrange: Create booking and login
        $renter = RenterFactory::new()
            ->withName('Show Test Renter')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Show Test Booking')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Request the booking show page
        $showUrl = '/admin/booking/'.$booking->getId().'/show';
        $this->client->request('GET', $showUrl);

        // Assert: Page renders successfully
        self::assertResponseIsSuccessful();
    }

    public function testStuffListPageRendersWithBookingData(): void
    {
        // Arrange: Create booking with items
        $renter = RenterFactory::new()
            ->withName('Stufflist Test Renter')
            ->create();

        $item = ItemFactory::new()
            ->withName('Stufflist Test Item')
            ->withRent('50.00')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Stufflist Test Booking')
            ->withItems([$item])
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Request the stufflist page
        $stuffListUrl = '/admin/booking/'.$booking->getId().'/stufflist';
        $this->client->request('GET', $stuffListUrl);

        // Assert: Page renders successfully with booking name
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', 'Stufflist Test Booking');
    }

    /* -------------------------------------------------------------------------
     * Story Part 7: Entity Relationship Tests
     * ------------------------------------------------------------------------- */

    public function testItemRentHistoryTracking(): void
    {
        // Arrange: Create item and booking
        $renter = RenterFactory::new()->create();

        $item = ItemFactory::new()
            ->withName('History Test Item')
            ->create();

        // Act: Create booking with item
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('History Test Booking')
            ->withItems([$item])
            ->create();

        // Refresh item to see relationship
        $this->em()->refresh($item);

        // Assert: Item's rent history contains the booking
        self::assertTrue($item->getRentHistory()->contains($booking));
    }

    public function testPackageItemsStatusCheck(): void
    {
        // Arrange: Create package with a broken item
        $workingItem = ItemFactory::new()
            ->withName('Working Package Item')
            ->rentable()
            ->create();

        $brokenItem = ItemFactory::new()
            ->withName('Broken Package Item')
            ->needsFixing()
            ->create();

        $package = PackageFactory::new()
            ->withName('Mixed Status Package')
            ->withItems([$workingItem, $brokenItem])
            ->create();

        // Assert: Package reports something is broken
        self::assertTrue($package->getIsSomethingBroken());

        // Assert: Can retrieve broken items
        $brokenItems = $package->getItemsNeedingFixing();
        self::assertCount(1, $brokenItems);
        self::assertTrue($brokenItems->contains($brokenItem));
    }

    public function testBookingDetectsBrokenItems(): void
    {
        // Arrange: Create booking with a broken item
        $renter = RenterFactory::new()->create();

        $brokenItem = ItemFactory::new()
            ->withName('Broken Booking Item')
            ->needsFixing()
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Broken Item Booking')
            ->withItems([$brokenItem])
            ->create();

        // Assert: Booking reports something is broken
        self::assertTrue($booking->getIsSomethingBroken());
    }

    public function testRenterBookingsRelationship(): void
    {
        // Arrange: Create a renter and multiple bookings
        $renter = RenterFactory::new()
            ->withName('Multi-Booking Renter')
            ->create();

        $booking1 = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Renter Booking 1')
            ->create();

        $booking2 = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Renter Booking 2')
            ->create();

        // Refresh renter to see relationships
        $this->em()->refresh($renter);

        // Assert: Renter has both bookings
        self::assertCount(2, $renter->getBookings());
        self::assertTrue($renter->getBookings()->contains($booking1));
        self::assertTrue($renter->getBookings()->contains($booking2));
    }

    /* -------------------------------------------------------------------------
     * Story Part 8: Booking Data Array Tests
     * ------------------------------------------------------------------------- */

    public function testBookingGetDataArrayIncludesAllInformation(): void
    {
        // Arrange: Create complete booking
        $renter = RenterFactory::new()->create();

        $item = ItemFactory::new()
            ->withName('Data Array Item')
            ->withRent('100.00')
            ->withCompensationPrice('400.00')
            ->create();

        $packageItem = ItemFactory::new()
            ->withName('Data Array Package Item')
            ->withRent('50.00')
            ->create();

        $package = PackageFactory::new()
            ->withName('Data Array Package')
            ->withRent('200.00')
            ->withCompensationPrice('800.00')
            ->withItems([$packageItem])
            ->create();

        $accessoryChoice = AccessoryChoiceFactory::new()
            ->withName('Data Array Accessory')
            ->withCompensationPrice(20)
            ->create();

        $accessory = AccessoryFactory::new()
            ->forChoice($accessoryChoice)
            ->withCount('2')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Data Array Test Booking')
            ->withActualPrice('250.00')
            ->withItems([$item])
            ->withPackages([$package])
            ->withAccessories([$accessory])
            ->create();

        // Act: Get data array
        $data = $booking->getDataArray();

        // Assert: Data array contains expected keys
        self::assertArrayHasKey('name', $data);
        self::assertArrayHasKey('date', $data);
        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('packages', $data);
        self::assertArrayHasKey('accessories', $data);
        self::assertArrayHasKey('rent', $data);
        self::assertArrayHasKey('compensation', $data);
        self::assertArrayHasKey('actualTotal', $data);

        // Assert: Name matches
        self::assertSame('Data Array Test Booking', $data['name']);

        // Assert: Items, packages, and accessories are present
        self::assertCount(1, $data['items']);
        self::assertCount(1, $data['packages']);
        self::assertCount(1, $data['accessories']);
    }

    /* -------------------------------------------------------------------------
     * Story Part 9: Item Access Control Tests
     * ------------------------------------------------------------------------- */

    public function testItemWithWhoCanRentPrivilege(): void
    {
        // Arrange: Create privilege and item
        $privilege = WhoCanRentChoiceFactory::new()
            ->withName('Members Only')
            ->create();

        $item = ItemFactory::new()
            ->withName('Restricted Item')
            ->withRent('100.00')
            ->withWhoCanRent($privilege)
            ->create();

        // Assert: Item has the privilege
        self::assertCount(1, $item->getWhoCanRent());
        self::assertTrue($item->getWhoCanRent()->contains($privilege));
    }

    public function testPackageWithWhoCanRentPrivilege(): void
    {
        // Arrange: Create privilege and package
        $privilege = WhoCanRentChoiceFactory::new()
            ->withName('Organizations Only')
            ->create();

        $package = PackageFactory::new()
            ->withName('Restricted Package')
            ->withRent('200.00')
            ->withWhoCanRent($privilege)
            ->create();

        // Assert: Package has the privilege
        self::assertCount(1, $package->getWhoCanRent());
        self::assertTrue($package->getWhoCanRent()->contains($privilege));
    }

    /* -------------------------------------------------------------------------
     * Story Part 10: Negative Path Tests
     * ------------------------------------------------------------------------- */

    public function testBookingEditDeniedToNonAdminUser(): void
    {
        // Arrange: Create booking and login as regular member
        $renter = RenterFactory::new()->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Access Denied Test Booking')
            ->create();

        $this->loginAsMember();

        // Act: Attempt to access booking edit page
        $this->client->request('GET', '/admin/booking/'.$booking->getId().'/edit');

        // Assert: Access denied
        $response = $this->client->getResponse();
        self::assertSame(403, $response->getStatusCode());
    }

    public function testBookingWithCancelledStatus(): void
    {
        // Arrange: Create and cancel a booking
        $renter = RenterFactory::new()->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Cancelled Booking Test')
            ->cancelled()
            ->create();

        // Assert: Booking is marked as cancelled
        self::assertTrue($booking->getCancelled());
    }

    public function testItemMarkedAsCannotBeRented(): void
    {
        // Arrange: Create item that cannot be rented
        $item = ItemFactory::new()
            ->withName('Unavailable Item')
            ->cannotBeRented()
            ->create();

        // Assert: Item is not rentable
        self::assertTrue($item->getCannotBeRented());
    }

    public function testItemMarkedAsSpareParts(): void
    {
        // Arrange: Create item for spare parts only
        $item = ItemFactory::new()
            ->withName('Spare Parts Item')
            ->spareParts()
            ->create();

        // Assert: Item is spare parts and cannot be rented
        self::assertTrue($item->getToSpareParts());
        self::assertTrue($item->getCannotBeRented());
    }

    public function testItemMarkedAsForSale(): void
    {
        // Arrange: Create item for sale
        $item = ItemFactory::new()
            ->withName('For Sale Item')
            ->forSale()
            ->create();

        // Assert: Item is marked for sale
        self::assertTrue($item->getForSale());
    }

    /* -------------------------------------------------------------------------
     * Story Part 11: Form Type Tests (ItemsType, PackagesType)
     * These tests submit the booking edit form to exercise the custom form types.
     *
     * Note: Items must have a category (child of 'item' root) to appear in the form.
     * ------------------------------------------------------------------------- */

    /**
     * Ensure Sonata Classification fixtures exist for ItemsType to work.
     * Returns the child category that items should be assigned to.
     */
    private function ensureClassificationFixtures(): \App\Entity\Sonata\SonataClassificationCategory
    {
        $em = $this->em();

        // Check if context 'default' exists
        $context = $em->getRepository(\App\Entity\Sonata\SonataClassificationContext::class)
            ->find('default');

        if (null === $context) {
            $context = new \App\Entity\Sonata\SonataClassificationContext();
            $context->setId('default');
            $context->setName('Default');
            $context->setEnabled(true);
            $em->persist($context);
            $em->flush();
        }

        // Check if a root category with slug 'item' exists
        $itemCategory = $em->getRepository(\App\Entity\Sonata\SonataClassificationCategory::class)
            ->findOneBy(['slug' => 'item']);

        if (null === $itemCategory) {
            // Create 'item' as a ROOT category (no parent)
            $itemCategory = new \App\Entity\Sonata\SonataClassificationCategory();
            $itemCategory->setContext($context);
            $itemCategory->setName('Item');
            $itemCategory->setSlug('item');
            $itemCategory->setEnabled(true);
            $em->persist($itemCategory);
            $em->flush();
        }

        // Check if child category exists
        $childCategory = $em->getRepository(\App\Entity\Sonata\SonataClassificationCategory::class)
            ->findOneBy(['slug' => 'audio-equipment']);

        if (null === $childCategory) {
            // Create a child category for item grouping
            $childCategory = new \App\Entity\Sonata\SonataClassificationCategory();
            $childCategory->setContext($context);
            $childCategory->setName('Audio Equipment');
            $childCategory->setSlug('audio-equipment');
            $childCategory->setEnabled(true);
            $childCategory->setParent($itemCategory);
            $em->persist($childCategory);
            $em->flush();
        }

        return $childCategory;
    }

    /**
     * @param array<string, mixed> $formValues
     */
    private function setFormFieldValueByName(
        array &$formValues,
        string $fieldName,
        string $value,
    ): void {
        $root = strstr($fieldName, '[', true);
        if (false === $root) {
            $root = $fieldName;
        }

        preg_match_all('/\[([^\]]*)\]/', $fieldName, $matches);
        $segments = $matches[1] ?? [];

        if (!isset($formValues[$root])) {
            $formValues[$root] = [];
        }

        $ref = &$formValues[$root];
        $last = array_pop($segments);
        if (null === $last || '' === $last) {
            $formValues[$root] = $value;

            return;
        }
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !\is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref[$last] = $value;
    }

    public function testBookingEditFormSubmitWithItemsSelected(): void
    {
        // Ensure required classification fixtures exist and get child category
        $category = $this->ensureClassificationFixtures();

        // Arrange: Create rentable items with category (required to appear in form)
        $renter = RenterFactory::new()
            ->withName('Form Items Test Renter')
            ->create();

        $item1 = ItemFactory::new()
            ->withName('Form Test Item Alpha')
            ->withRent('25.00')
            ->withCategory($category)
            ->rentable()
            ->create();

        $item2 = ItemFactory::new()
            ->withName('Form Test Item Beta')
            ->withRent('35.00')
            ->withCategory($category)
            ->rentable()
            ->create();

        // Store IDs before any EM operations
        $item1Id = $item1->getId();
        $item2Id = $item2->getId();

        // Create booking with name (required for Rentals tab to appear)
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Form Items Submit Test')
            ->create();

        $bookingId = $booking->getId();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load the booking edit form
        $crawler = $this->client->request('GET', '/admin/booking/'.$bookingId.'/edit');
        self::assertResponseIsSuccessful();

        // Find the main admin form
        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('input[name*="[name]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Could not find admin form with name field');

        // Get the form
        $form = $formNode->form();

        // Find checkboxes for items - they use value attribute with item ID
        $item1Checkbox = $crawler->filter('input[type="checkbox"][value="'.$item1Id.'"]');
        $item2Checkbox = $crawler->filter('input[type="checkbox"][value="'.$item2Id.'"]');

        // Debug: List all items checkboxes found in the form
        $allItemsCheckboxes = $crawler->filter('input[type="checkbox"][name*="[items]"]');
        $checkboxIds = [];
        foreach ($allItemsCheckboxes as $cb) {
            $checkboxIds[] = $cb->getAttribute('value');
        }

        // Assert: Check if checkboxes were found
        self::assertGreaterThan(
            0,
            $item1Checkbox->count(),
            'Item 1 (ID='.$item1Id.') checkbox not found. Available IDs: '.implode(', ', $checkboxIds)
        );
        self::assertGreaterThan(
            0,
            $item2Checkbox->count(),
            'Item 2 (ID='.$item2Id.') checkbox not found. Available IDs: '.implode(', ', $checkboxIds)
        );

        // Get the checkbox name attribute - all items checkboxes share the same name with []
        $item1Name = $item1Checkbox->attr('name');
        self::assertNotEmpty($item1Name, 'Item 1 checkbox has no name attribute');

        // Get the form's action URL
        $formAction = $form->getUri();
        $formMethod = $form->getMethod();

        // Build raw form data - use getPhpValues() as base and add items
        $formValues = $form->getPhpValues();
        $root = array_key_first($formValues);
        self::assertNotNull($root, 'Could not detect Sonata form root');

        // Get CSRF token from the form
        $csrfToken = $crawler->filter('input[name="'.$root.'[_token]"]')->attr('value');
        self::assertNotEmpty($csrfToken, 'CSRF token not found');
        $root = $root ?? array_key_first($formValues);
        self::assertNotNull($root, 'Could not detect form root');

        // Set items as array of entity IDs
        $formValues[$root]['items'] = [(string) $item1Id, (string) $item2Id];

        // Ensure packages is set (even if empty)
        if (!isset($formValues[$root]['packages'])) {
            $formValues[$root]['packages'] = [];
        }

        // Submit via raw POST (similar to GeneralShopCartSubmissionTest pattern)
        $this->client->request($formMethod, $formAction, $formValues);

        // Check if we got a redirect (success) or error
        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();

        // If 500 error, dump for debugging
        if (500 === $statusCode) {
            // Check if there are form errors displayed
            $errorCrawler = $this->client->getCrawler();
            $errors = $errorCrawler->filter('.sonata-ba-form-error, .help-block, .invalid-feedback');
            $errorMessages = [];
            foreach ($errors as $err) {
                $errorMessages[] = trim($err->textContent);
            }
            self::fail('Form submission returned 500. Errors: '.implode('; ', $errorMessages));
        }

        // Follow redirect if needed
        if ($response->isRedirect()) {
            $this->client->followRedirect();
        }

        // Assert: Form submission successful
        self::assertContains(
            $statusCode,
            [200, 302],
            'Form submission failed with status '.$statusCode
        );

        // Refresh booking from database
        $this->em()->clear();
        $updatedBooking = $this->em()->getRepository(\App\Entity\Rental\Booking\Booking::class)
            ->find($bookingId);

        // Assert: Items were added to booking
        self::assertCount(2, $updatedBooking->getItems());
    }

    public function testBookingEditFormSubmitWithPackagesSelected(): void
    {
        // Ensure required classification fixtures exist
        $this->ensureClassificationFixtures();

        // Arrange: Create package with items
        $renter = RenterFactory::new()
            ->withName('Form Packages Test Renter')
            ->create();

        $packageItem = ItemFactory::new()
            ->withName('Package Internal Item')
            ->withRent('20.00')
            ->create();

        $package = PackageFactory::new()
            ->withName('Form Test Package')
            ->withRent('75.00')
            ->withItems([$packageItem])
            ->create();

        // Create booking with name
        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Form Packages Submit Test')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load the booking edit form
        $crawler = $this->client->request('GET', '/admin/booking/'.$booking->getId().'/edit');
        self::assertResponseIsSuccessful();

        // Find the main admin form
        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('input[name*="[name]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Could not find admin form');

        $form = $formNode->form();
        $values = $form->getPhpValues();

        $root = array_key_first($values);
        self::assertNotNull($root, 'Could not detect form root');

        // Set packages as array of entity IDs (this exercises PackagesType)
        $values[$root]['packages'] = [(string) $package->getId()];

        // Submit the form
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // Assert: Form submission successful
        $response = $this->client->getResponse();
        self::assertContains(
            $response->getStatusCode(),
            [200, 302],
            'Form submission failed'
        );

        // Refresh booking from database
        $this->em()->clear();
        $updatedBooking = $this->em()->getRepository(\App\Entity\Rental\Booking\Booking::class)
            ->find($booking->getId());

        // Assert: Package was added to booking
        self::assertCount(1, $updatedBooking->getPackages());
    }

    public function testBookingEditFormRendersItemsTypeChoices(): void
    {
        // Ensure required classification fixtures exist and get child category
        $category = $this->ensureClassificationFixtures();

        // Arrange: Create item with category (required to appear in form)
        $renter = RenterFactory::new()->create();

        $item = ItemFactory::new()
            ->withName('Visible Choice Item')
            ->withRent('50.00')
            ->withCategory($category)
            ->rentable()
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Items Choice Test')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load the booking edit form
        $crawler = $this->client->request('GET', '/admin/booking/'.$booking->getId().'/edit');
        self::assertResponseIsSuccessful();

        // Assert: The item appears as a choice in the form (exercises ItemsType::configureOptions)
        self::assertGreaterThan(
            0,
            $crawler
                ->filterXPath('//*[contains(normalize-space(.), "Visible Choice Item")]')
                ->count(),
            'Expected item to appear as an option in the form.',
        );

        // Verify item was created
        self::assertNotNull($item->getId());
    }

    public function testBookingEditFormDoesNotRenderDecommissionedItemChoices(): void
    {
        $category = $this->ensureClassificationFixtures();

        $renter = RenterFactory::new()->create();

        $visibleItem = ItemFactory::new()
            ->withName('Visible Active Choice Item')
            ->withRent('45.00')
            ->withCategory($category)
            ->rentable()
            ->create();

        $decommissionedItem = ItemFactory::new()
            ->withName('Decommissioned Hidden Choice Item')
            ->withRent('45.00')
            ->withCategory($category)
            ->rentable()
            ->create();
        $decommissionedItem->setDecommissioned(true);
        $this->em()->flush();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Decommissioned Choice Filter Test')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', '/admin/booking/'.$booking->getId().'/edit');
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(
            0,
            $crawler
                ->filterXPath('//*[contains(normalize-space(.), "Visible Active Choice Item")]')
                ->count(),
            'Expected active item to appear as an option in the form.',
        );
        self::assertSame(
            0,
            $crawler
                ->filterXPath('//*[contains(normalize-space(.), "Decommissioned Hidden Choice Item")]')
                ->count(),
            'Expected decommissioned item to be hidden from booking item choices.',
        );

        self::assertNotNull($visibleItem->getId());
        self::assertNotNull($decommissionedItem->getId());
    }

    public function testBookingEditFormRendersPackagesTypeChoices(): void
    {
        // Ensure required classification fixtures exist
        $this->ensureClassificationFixtures();

        // Arrange: Create package that should appear in the form
        $renter = RenterFactory::new()->create();

        $package = PackageFactory::new()
            ->withName('Visible Package Choice')
            ->withRent('100.00')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Packages Choice Test')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load the booking edit form
        $crawler = $this->client->request('GET', '/admin/booking/'.$booking->getId().'/edit');
        self::assertResponseIsSuccessful();

        // Assert: The package appears as a choice in the form (exercises PackagesType::configureOptions)
        self::assertGreaterThan(
            0,
            $crawler
                ->filterXPath('//*[contains(normalize-space(.), "Visible Package Choice")]')
                ->count(),
            'Expected package to appear as an option in the form.',
        );

        // Verify package was created
        self::assertNotNull($package->getId());
    }

    /* -------------------------------------------------------------------------
     * Story Part 12: StatusEventAdmin Tests (Child Admin for Booking Status)
     *
     * Tests the StatusEventAdmin which allows changing booking status through
     * a child admin interface at /admin/booking/{id}/status-event/create
     * ------------------------------------------------------------------------- */

    public function testStatusEventCreateFormLoadsForBooking(): void
    {
        // Arrange: Create a booking
        $renter = RenterFactory::new()
            ->withName('Status Event Test Renter')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Status Event Form Test')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load the StatusEvent create form (child admin)
        $createUrl = '/admin/booking/'.$booking->getId().'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);

        // Assert: Form loads successfully
        self::assertResponseIsSuccessful();

        // Assert: Form contains expected booking status fields
        $this->client->assertSelectorExists('form');
        $this->client->assertSelectorExists('input[name*="cancelled"]');
        $this->client->assertSelectorExists('input[name*="itemsReturned"]');
        $this->client->assertSelectorExists('input[name*="invoiceSent"]');
        $this->client->assertSelectorExists('input[name*="paid"]');
        $this->client->assertSelectorExists('textarea[name*="description"]');
    }

    public function testStatusEventSubmitMarkBookingItemsReturned(): void
    {
        // Arrange: Create a booking that hasn't had items returned yet
        $renter = RenterFactory::new()
            ->withName('Items Returned Test Renter')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Items Returned Test Booking')
            ->create();

        $bookingId = $booking->getId();

        // Verify initial state
        self::assertFalse($booking->getItemsReturned());

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load the StatusEvent create form
        $createUrl = '/admin/booking/'.$bookingId.'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);
        self::assertResponseIsSuccessful();

        // Find the form that contains the description field
        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('textarea[name*="[description]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Form with description field not found');

        $form = $formNode->form();

        // Get form values as base
        $formValues = $form->getPhpValues();

        $descriptionName = $formNode
            ->filter('textarea[name*="[description]"]')
            ->attr('name');
        self::assertNotNull($descriptionName, 'Description field name not found');

        $itemsReturnedName = $formNode
            ->filter('input[name*="itemsReturned"]')
            ->attr('name');
        self::assertNotNull($itemsReturnedName, 'ItemsReturned field name not found');

        // Set the items returned checkbox and description
        $this->setFormFieldValueByName(
            $formValues,
            $descriptionName,
            'All items returned in good condition',
        );
        $this->setFormFieldValueByName($formValues, $itemsReturnedName, '1');

        // Submit the form
        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        // Should redirect on success
        $response = $this->client->getResponse();

        // If not redirect, check for form errors
        if (!$response->isRedirect()) {
            $errorCrawler = $this->client->getCrawler();
            $errors = $errorCrawler->filter('.sonata-ba-field-error, .help-block, .invalid-feedback, .form-error-message, .alert-danger, .has-error');
            $errorMessages = [];
            foreach ($errors as $err) {
                $text = trim($err->textContent);
                if (!empty($text)) {
                    $errorMessages[] = $text;
                }
            }
            self::fail(
                'Expected redirect after status event creation, got status: '.$response->getStatusCode().
                '. Form errors: '.implode('; ', $errorMessages).
                '. Form values submitted: '.json_encode($formValues)
            );
        }

        // Refresh booking from database
        $this->em()->clear();
        $updatedBooking = $this->em()->getRepository(\App\Entity\Rental\Booking\Booking::class)
            ->find($bookingId);

        // Assert: Booking status was updated
        self::assertTrue($updatedBooking->getItemsReturned(), 'Booking should be marked as items returned');

        // Assert: StatusEvent was created
        $statusEvents = $updatedBooking->getStatusEvents();
        self::assertGreaterThan(0, $statusEvents->count(), 'StatusEvent should be created');

        $latestEvent = $statusEvents->last();
        self::assertSame('All items returned in good condition', $latestEvent->getDescription());
        self::assertSame($updatedBooking, $latestEvent->getBooking());
    }

    public function testStatusEventSubmitMarkItemNeedsFixing(): void
    {
        $item = ItemFactory::new()->cloneable()->create();
        $itemId = $item->getId();

        self::assertFalse($item->getNeedsFixing());
        self::assertFalse($item->getCannotBeRented());

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $createUrl = '/admin/item/'.$itemId.'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);
        self::assertResponseIsSuccessful();

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('textarea[name*="[description]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Form with description field not found');

        $form = $formNode->form();
        $formValues = $form->getPhpValues();

        $descriptionName = $formNode
            ->filter('textarea[name*="[description]"]')
            ->attr('name');
        self::assertNotNull($descriptionName, 'Description field name not found');

        $needsFixingName = $formNode
            ->filter('input[name*="needsFixing"]')
            ->attr('name');
        self::assertNotNull($needsFixingName, 'NeedsFixing field name not found');

        $cannotBeRentedName = $formNode
            ->filter('input[name*="cannotBeRented"]')
            ->attr('name');
        self::assertNotNull($cannotBeRentedName, 'CannotBeRented field name not found');

        $this->setFormFieldValueByName(
            $formValues,
            $descriptionName,
            'Item requires fixing',
        );
        $this->setFormFieldValueByName($formValues, $needsFixingName, '1');
        $this->setFormFieldValueByName($formValues, $cannotBeRentedName, '1');

        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        $response = $this->client->getResponse();
        if (!$response->isRedirect()) {
            $errorCrawler = $this->client->getCrawler();
            $errors = $errorCrawler->filter('.sonata-ba-field-error, .help-block, .invalid-feedback, .form-error-message, .alert-danger, .has-error');
            $errorMessages = [];
            foreach ($errors as $err) {
                $text = trim($err->textContent);
                if (!empty($text)) {
                    $errorMessages[] = $text;
                }
            }
            self::fail(
                'Expected redirect after status event creation, got status: '.$response->getStatusCode().
                '. Form errors: '.implode('; ', $errorMessages).
                '. Form values submitted: '.json_encode($formValues)
            );
        }

        $this->em()->clear();
        $updatedItem = $this->em()->getRepository(\App\Entity\Rental\Inventory\Item::class)
            ->find($itemId);
        self::assertNotNull($updatedItem);

        self::assertTrue($updatedItem->getNeedsFixing(), 'Item should be marked as needs fixing');
        self::assertTrue($updatedItem->getCannotBeRented(), 'Item should be marked as cannot be rented');

        $events = $updatedItem->getFixingHistory();
        self::assertGreaterThan(0, $events->count(), 'StatusEvent should be created for item');
        $latestEvent = $events->last();
        self::assertSame('Item requires fixing', $latestEvent->getDescription());
        self::assertSame($updatedItem, $latestEvent->getItem());
    }

    public function testStatusEventSubmitMarksItemDecommissioned(): void
    {
        $item = ItemFactory::new()->cloneable()->create();
        $itemId = $item->getId();

        self::assertFalse($item->getDecommissioned());

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $createUrl = '/admin/item/'.$itemId.'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);
        self::assertResponseIsSuccessful();

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('textarea[name*="[description]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Form with description field not found');

        $form = $formNode->form();
        $formValues = $form->getPhpValues();

        $descriptionName = $formNode
            ->filter('textarea[name*="[description]"]')
            ->attr('name');
        self::assertNotNull($descriptionName, 'Description field name not found');

        $decommissionedName = $formNode
            ->filter('input[name*="decommissioned"]')
            ->attr('name');
        self::assertNotNull($decommissionedName, 'Decommissioned field name not found');

        $this->setFormFieldValueByName(
            $formValues,
            $descriptionName,
            'Item removed from active inventory',
        );
        $this->setFormFieldValueByName($formValues, $decommissionedName, '1');

        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        $response = $this->client->getResponse();
        if (!$response->isRedirect()) {
            $errorCrawler = $this->client->getCrawler();
            $errors = $errorCrawler->filter('.sonata-ba-field-error, .help-block, .invalid-feedback, .form-error-message, .alert-danger, .has-error');
            $errorMessages = [];
            foreach ($errors as $err) {
                $text = trim($err->textContent);
                if (!empty($text)) {
                    $errorMessages[] = $text;
                }
            }
            self::fail(
                'Expected redirect after status event creation, got status: '.$response->getStatusCode().
                '. Form errors: '.implode('; ', $errorMessages).
                '. Form values submitted: '.json_encode($formValues)
            );
        }

        $this->em()->clear();
        $updatedItem = $this->em()->getRepository(\App\Entity\Rental\Inventory\Item::class)
            ->find($itemId);
        self::assertNotNull($updatedItem);

        self::assertTrue($updatedItem->getDecommissioned(), 'Item should be marked as decommissioned');

        $events = $updatedItem->getFixingHistory();
        self::assertGreaterThan(0, $events->count(), 'StatusEvent should be created for item');
        $latestEvent = $events->last();
        self::assertSame('Item removed from active inventory', $latestEvent->getDescription());
        self::assertSame($updatedItem, $latestEvent->getItem());
    }

    public function testItemEditFormDoesNotExposeItemStatusFlags(): void
    {
        $item = ItemFactory::new()->cloneable()->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $crawler = $this->client->request('GET', '/admin/item/'.$item->getId().'/edit');
        self::assertResponseIsSuccessful();

        self::assertSame(0, $crawler->filter('input[name*="needsFixing"]')->count());
        self::assertSame(0, $crawler->filter('input[name*="cannotBeRented"]')->count());
        self::assertSame(0, $crawler->filter('input[name*="toSpareParts"]')->count());
        self::assertSame(0, $crawler->filter('input[name*="forSale"]')->count());
        self::assertSame(0, $crawler->filter('input[name*="decommissioned"]')->count());
    }

    public function testStatusEventSubmitMarkBookingCancelled(): void
    {
        // Arrange: Create a booking
        $renter = RenterFactory::new()
            ->withName('Cancellation Test Renter')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Cancellation Test Booking')
            ->create();

        $bookingId = $booking->getId();

        // Verify initial state
        self::assertFalse($booking->getCancelled());

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load and submit the StatusEvent form
        $createUrl = '/admin/booking/'.$bookingId.'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);
        self::assertResponseIsSuccessful();

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('textarea[name*="[description]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Form with description field not found');
        $form = $formNode->form();
        $formValues = $form->getPhpValues();

        $descriptionName = $formNode
            ->filter('textarea[name*="[description]"]')
            ->attr('name');
        self::assertNotNull($descriptionName, 'Description field name not found');

        $cancelledName = $formNode
            ->filter('input[name*="cancelled"]')
            ->attr('name');
        self::assertNotNull($cancelledName, 'Cancelled field name not found');

        // Mark as cancelled
        $this->setFormFieldValueByName(
            $formValues,
            $descriptionName,
            'Customer cancelled the booking',
        );
        $this->setFormFieldValueByName($formValues, $cancelledName, '1');

        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        // Assert: Redirect on success
        self::assertTrue($this->client->getResponse()->isRedirect());

        // Verify booking was cancelled
        $this->em()->clear();
        $updatedBooking = $this->em()->getRepository(\App\Entity\Rental\Booking\Booking::class)
            ->find($bookingId);

        self::assertTrue($updatedBooking->getCancelled(), 'Booking should be marked as cancelled');
    }

    public function testStatusEventSubmitMarkBookingPaid(): void
    {
        // Arrange: Create a booking with items returned (typical flow before marking paid)
        $renter = RenterFactory::new()
            ->withName('Payment Test Renter')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Payment Test Booking')
            ->create();

        // Mark items as returned first (typical workflow)
        $booking->setItemsReturned(true);
        $booking->setInvoiceSent(true);
        $this->em()->flush();

        $bookingId = $booking->getId();

        // Verify initial state
        self::assertFalse($booking->getPaid());

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: Load and submit the StatusEvent form
        $createUrl = '/admin/booking/'.$bookingId.'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);
        self::assertResponseIsSuccessful();

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('textarea[name*="[description]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Form with description field not found');
        $form = $formNode->form();
        $formValues = $form->getPhpValues();

        $descriptionName = $formNode
            ->filter('textarea[name*="[description]"]')
            ->attr('name');
        self::assertNotNull($descriptionName, 'Description field name not found');

        $itemsReturnedName = $formNode
            ->filter('input[name*="itemsReturned"]')
            ->attr('name');
        self::assertNotNull($itemsReturnedName, 'ItemsReturned field name not found');
        $invoiceSentName = $formNode
            ->filter('input[name*="invoiceSent"]')
            ->attr('name');
        self::assertNotNull($invoiceSentName, 'InvoiceSent field name not found');
        $paidName = $formNode->filter('input[name*="paid"]')->attr('name');
        self::assertNotNull($paidName, 'Paid field name not found');

        // Mark as paid (keep existing statuses)
        $this->setFormFieldValueByName(
            $formValues,
            $descriptionName,
            'Payment received via bank transfer',
        );
        $this->setFormFieldValueByName($formValues, $itemsReturnedName, '1');
        $this->setFormFieldValueByName($formValues, $invoiceSentName, '1');
        $this->setFormFieldValueByName($formValues, $paidName, '1');

        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        // Assert: Redirect on success
        self::assertTrue($this->client->getResponse()->isRedirect());

        // Verify booking was marked as paid
        $this->em()->clear();
        $updatedBooking = $this->em()->getRepository(\App\Entity\Rental\Booking\Booking::class)
            ->find($bookingId);

        self::assertTrue($updatedBooking->getPaid(), 'Booking should be marked as paid');
        self::assertNotNull($updatedBooking->getPaidDate(), 'Paid date should be set');
    }

    public function testStatusEventListShowsBookingEvents(): void
    {
        // Arrange: Create a booking with a status event
        $renter = RenterFactory::new()
            ->withName('Status List Test Renter')
            ->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Status List Test Booking')
            ->create();

        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // First create a status event
        $createUrl = '/admin/booking/'.$booking->getId().'/status-event/create';
        $crawler = $this->client->request('GET', $createUrl);

        $formNode = null;
        foreach ($crawler->filter('form')->each(static fn ($n) => $n) as $candidate) {
            if ($candidate->filter('textarea[name*="[description]"]')->count() > 0) {
                $formNode = $candidate;
                break;
            }
        }
        self::assertNotNull($formNode, 'Form with description field not found');
        $form = $formNode->form();
        $formValues = $form->getPhpValues();

        $descriptionName = $formNode
            ->filter('textarea[name*="[description]"]')
            ->attr('name');
        self::assertNotNull($descriptionName, 'Description field name not found');

        $itemsReturnedName = $formNode
            ->filter('input[name*="itemsReturned"]')
            ->attr('name');
        self::assertNotNull($itemsReturnedName, 'ItemsReturned field name not found');

        $this->setFormFieldValueByName(
            $formValues,
            $descriptionName,
            'Test status event for list',
        );
        $this->setFormFieldValueByName($formValues, $itemsReturnedName, '1');

        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        // Act: Load the status event list for this booking
        $listUrl = '/admin/booking/'.$booking->getId().'/status-event/list';
        $this->client->request('GET', $listUrl);

        // Assert: List page loads successfully
        self::assertResponseIsSuccessful();
    }

    public function testStatusEventAdminDeniedToNonAdmin(): void
    {
        // Arrange: Create a booking
        $renter = RenterFactory::new()->create();

        $booking = BookingFactory::new()
            ->forRenter($renter)
            ->withName('Access Denied Status Test')
            ->create();

        // Login as regular member (not admin)
        $this->loginAsMember();

        // Act: Try to access StatusEvent create form
        $createUrl = '/admin/booking/'.$booking->getId().'/status-event/create';
        $this->client->request('GET', $createUrl);

        // Assert: Access denied (403)
        $response = $this->client->getResponse();
        self::assertSame(403, $response->getStatusCode());
    }
}
