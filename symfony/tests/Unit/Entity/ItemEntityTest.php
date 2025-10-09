<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Booking;
use App\Entity\File;
use App\Entity\Item;
use App\Entity\Package;
use App\Entity\Sonata\SonataClassificationCategory as Category;
use App\Entity\Sonata\SonataClassificationTag as Tag;
use App\Entity\StatusEvent;
use App\Entity\User;
use App\Entity\WhoCanRentChoice;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class ItemEntityTest extends TestCase
{
    public function testConstructorInitializesCollections(): void
    {
        $item = new Item();
        $this->assertInstanceOf(ArrayCollection::class, $item->getTags());
        $this->assertInstanceOf(ArrayCollection::class, $item->getWhoCanRent());
        $this->assertInstanceOf(ArrayCollection::class, $item->getFiles());
        $this->assertInstanceOf(
            ArrayCollection::class,
            $item->getRentHistory(),
        );
        $this->assertInstanceOf(ArrayCollection::class, $item->getPackages());
        $this->assertInstanceOf(
            ArrayCollection::class,
            $item->getFixingHistory(),
        );
    }

    public function testSetAndGetScalarFields(): void
    {
        $item = new Item();
        $item->setName('TestItem');
        $item->setManufacturer('TestMaker');
        $item->setModel('ModelX');
        $item->setUrl('http://example.com');
        $item->setSerialnumber('SN123');
        $item->setPlaceinstorage('Shelf 42');
        $item->setDescription('A test item');
        $item->setRent('12.34');
        $item->setCompensationPrice('99.99');
        $item->setRentNotice('Handle with care');
        $item->setNeedsFixing(true);
        $item->setForSale(true);
        $item->setToSpareParts(true);
        $item->setCannotBeRented(true);
        $item->setPurchasePrice('55.55');
        $item->setCommission(new \DateTime('2025-01-01'));
        $item->setCreatedAt(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $item->setUpdatedAt(new \DateTimeImmutable('2025-01-02 10:00:00'));

        $this->assertSame('TestItem', $item->getName());
        $this->assertSame('TestMaker', $item->getManufacturer());
        $this->assertSame('ModelX', $item->getModel());
        $this->assertSame('http://example.com', $item->getUrl());
        $this->assertSame('SN123', $item->getSerialnumber());
        $this->assertSame('Shelf 42', $item->getPlaceinstorage());
        $this->assertSame('A test item', $item->getDescription());
        $this->assertSame('12.34', $item->getRent());
        $this->assertSame('99.99', $item->getCompensationPrice());
        $this->assertSame('Handle with care', $item->getRentNotice());
        $this->assertTrue($item->getNeedsFixing());
        $this->assertTrue($item->getForSale());
        $this->assertTrue($item->getToSpareParts());
        $this->assertTrue($item->getCannotBeRented());
        $this->assertSame('55.55', $item->getPurchasePrice());
        $this->assertInstanceOf(\DateTime::class, $item->getCommission());
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $item->getCreatedAt(),
        );
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $item->getUpdatedAt(),
        );
    }

    public function testAddAndRemoveTags(): void
    {
        $item = new Item();
        $tag = $this->createMock(Tag::class);

        $item->addTag($tag);
        $this->assertTrue($item->getTags()->contains($tag));

        $item->removeTag($tag);
        $this->assertFalse($item->getTags()->contains($tag));
    }

    public function testAddAndRemoveWhoCanRent(): void
    {
        $item = new Item();
        $who = $this->createMock(WhoCanRentChoice::class);

        $item->addWhoCanRent($who);
        $this->assertTrue($item->getWhoCanRent()->contains($who));

        $item->removeWhoCanRent($who);
        $this->assertFalse($item->getWhoCanRent()->contains($who));
    }

    public function testAddAndRemoveFiles(): void
    {
        $item = new Item();
        $file = $this->createMock(File::class);
        $file->expects($this->any())->method('setProduct');

        $item->addFile($file);
        $this->assertTrue($item->getFiles()->contains($file));

        $item->removeFile($file);
        $this->assertFalse($item->getFiles()->contains($file));
    }

    public function testAddAndRemovePackages(): void
    {
        $item = new Item();
        $package = $this->createMock(Package::class);

        $item->addPackage($package);
        $this->assertTrue($item->getPackages()->contains($package));

        $item->removePackage($package);
        $this->assertFalse($item->getPackages()->contains($package));
    }

    public function testAddAndRemoveRentHistory(): void
    {
        $item = new Item();
        $booking = $this->createMock(Booking::class);

        $item->addRentHistory($booking);
        $this->assertTrue($item->getRentHistory()->contains($booking));

        $item->removeRentHistory($booking);
        $this->assertFalse($item->getRentHistory()->contains($booking));
    }

    public function testAddAndRemoveFixingHistory(): void
    {
        $item = new Item();
        $event = $this->createMock(StatusEvent::class);
        $event->expects($this->any())->method('setItem');

        $item->addFixingHistory($event);
        $this->assertTrue($item->getFixingHistory()->contains($event));

        $item->removeFixingHistory($event);
        $this->assertFalse($item->getFixingHistory()->contains($event));
    }

    public function testSetAndGetCategory(): void
    {
        $item = new Item();
        $category = $this->createMock(Category::class);

        $item->setCategory($category);
        $this->assertSame($category, $item->getCategory());

        $item->setCategory(null);
        $this->assertNull($item->getCategory());
    }

    public function testSetAndGetCreatorModifier(): void
    {
        $item = new Item();
        $creator = $this->createMock(User::class);
        $modifier = $this->createMock(User::class);

        $item->setCreator($creator);
        $item->setModifier($modifier);

        $this->assertSame($creator, $item->getCreator());
        $this->assertSame($modifier, $item->getModifier());

        $item->setCreator(null);
        $item->setModifier(null);

        $this->assertNull($item->getCreator());
        $this->assertNull($item->getModifier());
    }

    public function testLifecycleCallbacks(): void
    {
        $item = new Item();
        $item->prePersist();
        $createdAt = $item->getCreatedAt();
        $updatedAt = $item->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        // Compare up to seconds, ignore microseconds
        $this->assertSame(
            $createdAt->format('Y-m-d H:i:s'),
            $updatedAt->format('Y-m-d H:i:s'),
        );

        // Simulate update
        sleep(1);
        $item->preUpdate();
        $this->assertNotEquals(
            $createdAt->format('Y-m-d H:i:s'),
            $item->getUpdatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testToStringReturnsNameOrNA(): void
    {
        $item = new Item();
        $item->setName('TestItem');
        $this->assertSame('TestItem', (string) $item);

        $item->setName('');
        $this->assertSame('N/A', (string) $item);
    }

    public function testEdgeCaseSetters(): void
    {
        $item = new Item();
        $item->setName('');
        $item->setManufacturer('');
        $item->setModel('');
        $item->setUrl('');
        $item->setSerialnumber('');
        $item->setPlaceinstorage('');
        $item->setDescription('');
        $item->setRent('');
        $item->setCompensationPrice('');
        $item->setRentNotice('');
        $item->setNeedsFixing(false);
        $item->setForSale(false);
        $item->setToSpareParts(false);
        $item->setCannotBeRented(false);
        $item->setPurchasePrice('');
        $item->setCommission(new \DateTime('2025-01-01'));
        $item->setCreatedAt(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $item->setUpdatedAt(new \DateTimeImmutable('2025-01-02 10:00:00'));

        $this->assertSame('', $item->getName());
        $this->assertSame('', $item->getManufacturer());
        $this->assertSame('', $item->getModel());
        $this->assertSame('', $item->getUrl());
        $this->assertSame('', $item->getSerialnumber());
        $this->assertSame('', $item->getPlaceinstorage());
        $this->assertSame('', $item->getDescription());
        $this->assertSame('', $item->getRent());
        $this->assertSame('', $item->getCompensationPrice());
        $this->assertSame('', $item->getRentNotice());
        $this->assertFalse($item->getNeedsFixing());
        $this->assertFalse($item->getForSale());
        $this->assertFalse($item->getToSpareParts());
        $this->assertFalse($item->getCannotBeRented());
        $this->assertSame('', $item->getPurchasePrice());
        $this->assertInstanceOf(\DateTime::class, $item->getCommission());
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $item->getCreatedAt(),
        );
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $item->getUpdatedAt(),
        );
    }
}
