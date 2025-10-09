<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\Happening;
use App\Entity\HappeningBooking;
use App\Entity\Member;
use App\Entity\Sonata\SonataMediaMedia;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Happening
 */
final class HappeningEntityTest extends TestCase
{
    public function testConstructorInitializesCollections(): void
    {
        $happening = new Happening();
        $this->assertInstanceOf(
            ArrayCollection::class,
            $happening->getBookings(),
        );
        $this->assertInstanceOf(
            ArrayCollection::class,
            $happening->getOwners(),
        );
        $this->assertCount(0, $happening->getBookings());
        $this->assertCount(0, $happening->getOwners());
    }

    public function testSetAndGetScalarFields(): void
    {
        $happening = new Happening();

        $happening->setNameFi('NimiFI');
        $happening->setNameEn('NameEN');
        $happening->setDescriptionFi('KuvausFI');
        $happening->setDescriptionEn('DescriptionEN');
        $dt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $happening->setTime($dt);
        $happening->setNeedsPreliminarySignUp(true);
        $happening->setNeedsPreliminaryPayment(true);
        $happening->setPaymentInfoFi('PayFI');
        $happening->setPaymentInfoEn('PayEN');
        $happening->setType('event');
        $happening->setMaxSignUps(42);
        $happening->setSlugFi('slug-fi');
        $happening->setSlugEn('slug-en');
        $happening->setPriceFi('10');
        $happening->setPriceEn('20');
        $happening->setReleaseThisHappeningInEvent(true);
        $happening->setSignUpsOpenUntil($dt);
        $happening->setAllowSignUpComments(false);

        $this->assertSame('NimiFI', $happening->getNameFi());
        $this->assertSame('NameEN', $happening->getNameEn());
        $this->assertSame('KuvausFI', $happening->getDescriptionFi());
        $this->assertSame('DescriptionEN', $happening->getDescriptionEn());
        $this->assertSame($dt, $happening->getTime());
        $this->assertTrue($happening->isNeedsPreliminarySignUp());
        $this->assertTrue($happening->isNeedsPreliminaryPayment());
        $this->assertSame('PayFI', $happening->getPaymentInfoFi());
        $this->assertSame('PayEN', $happening->getPaymentInfoEn());
        $this->assertSame('event', $happening->getType());
        $this->assertSame(42, $happening->getMaxSignUps());
        $this->assertSame('slug-fi', $happening->getSlugFi());
        $this->assertSame('slug-en', $happening->getSlugEn());
        $this->assertSame('10', $happening->getPriceFi());
        $this->assertSame('20', $happening->getPriceEn());
        $this->assertTrue($happening->isReleaseThisHappeningInEvent());
        $this->assertSame($dt, $happening->getSignUpsOpenUntil());
        $this->assertFalse($happening->isAllowSignUpComments());
    }

    public function testLocaleSensitiveGetters(): void
    {
        $happening = new Happening();
        $happening->setNameFi('NimiFI');
        $happening->setNameEn('NameEN');
        $happening->setSlugFi('slug-fi');
        $happening->setSlugEn('slug-en');
        $happening->setDescriptionFi('KuvausFI');
        $happening->setDescriptionEn('DescriptionEN');
        $happening->setPaymentInfoFi('PayFI');
        $happening->setPaymentInfoEn('PayEN');
        $happening->setPriceFi('10');
        $happening->setPriceEn('20');

        $this->assertSame('NimiFI', $happening->getName('fi'));
        $this->assertSame('NameEN', $happening->getName('en'));
        $this->assertSame('slug-fi', $happening->getSlug('fi'));
        $this->assertSame('slug-en', $happening->getSlug('en'));
        $this->assertSame('KuvausFI', $happening->getDescription('fi'));
        $this->assertSame('DescriptionEN', $happening->getDescription('en'));
        $this->assertSame('PayFI', $happening->getPaymentInfo('fi'));
        $this->assertSame('PayEN', $happening->getPaymentInfo('en'));
        $this->assertSame('10', $happening->getPrice('fi'));
        $this->assertSame('20', $happening->getPrice('en'));
    }

    public function testAddAndRemoveBookings(): void
    {
        $happening = new Happening();
        $booking = $this->createMock(HappeningBooking::class);
        $booking->expects($this->any())->method('setHappening');

        $happening->addBooking($booking);
        $this->assertTrue($happening->getBookings()->contains($booking));

        $booking
            ->expects($this->any())
            ->method('getHappening')
            ->willReturn($happening);

        $happening->removeBooking($booking);
        $this->assertFalse($happening->getBookings()->contains($booking));
    }

    public function testAddAndRemoveOwners(): void
    {
        $happening = new Happening();
        $owner = $this->createMock(Member::class);

        $happening->addOwner($owner);
        $this->assertTrue($happening->getOwners()->contains($owner));

        $happening->removeOwner($owner);
        $this->assertFalse($happening->getOwners()->contains($owner));
    }

    public function testSetAndGetPicture(): void
    {
        $happening = new Happening();
        $picture = $this->createMock(SonataMediaMedia::class);

        $happening->setPicture($picture);
        $this->assertSame($picture, $happening->getPicture());

        $happening->setPicture(null);
        $this->assertNull($happening->getPicture());
    }

    public function testSetAndGetEvent(): void
    {
        $happening = new Happening();
        $event = $this->createMock(Event::class);

        $happening->setEvent($event);
        $this->assertSame($event, $happening->getEvent());

        $happening->setEvent(null);
        $this->assertNull($happening->getEvent());
    }

    public function testSignUpsAreOpenLogic(): void
    {
        $happening = new Happening();
        $this->assertTrue($happening->signUpsAreOpen());

        $future = new \DateTimeImmutable('+1 day');
        $happening->setSignUpsOpenUntil($future);
        $this->assertTrue($happening->signUpsAreOpen());

        $past = new \DateTimeImmutable('-1 day');
        $happening->setSignUpsOpenUntil($past);
        $this->assertFalse($happening->signUpsAreOpen());
    }

    public function testToStringReturnsNameEn(): void
    {
        $happening = new Happening();
        $happening->setNameEn('TestEventEN');
        $this->assertSame('TestEventEN', (string) $happening);

        $happening->setNameEn('');
        $this->assertSame('', (string) $happening);
    }

    public function testEdgeCaseSetters(): void
    {
        $happening = new Happening();
        $happening->setNameFi('');
        $happening->setNameEn('');
        $happening->setDescriptionFi('');
        $happening->setDescriptionEn('');
        $happening->setType('');
        $happening->setSlugFi('');
        $happening->setSlugEn('');
        $happening->setPaymentInfoFi(null);
        $happening->setPaymentInfoEn(null);
        $happening->setPriceFi(null);
        $happening->setPriceEn(null);
        $happening->setEvent(null);
        $happening->setPicture(null);
        $happening->setSignUpsOpenUntil(null);

        $this->assertSame('', $happening->getNameFi());
        $this->assertSame('', $happening->getNameEn());
        $this->assertSame('', $happening->getDescriptionFi());
        $this->assertSame('', $happening->getDescriptionEn());
        $this->assertSame('', $happening->getType());
        $this->assertSame('', $happening->getSlugFi());
        $this->assertSame('', $happening->getSlugEn());
        $this->assertNull($happening->getPaymentInfoFi());
        $this->assertNull($happening->getPaymentInfoEn());
        $this->assertNull($happening->getPriceFi());
        $this->assertNull($happening->getPriceEn());
        $this->assertNull($happening->getEvent());
        $this->assertNull($happening->getPicture());
        $this->assertNull($happening->getSignUpsOpenUntil());
    }
}
