<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Contract;
use App\Entity\Rental\Booking\Renter;
use App\Tests\_Base\FixturesWebTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\Rental\BookingContractController
 */
final class BookingContractControllerTest extends FixturesWebTestCase
{
    private ObjectManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initSiteAwareClient();
        $this->client = $this->client();
        $this->entityManager = $this->em();
    }

    public function testContractPageLoadsAndShowsConsentForm(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="booking_consent"]');
        $this->client->assertSelectorExists('input[name="booking_consent[renterSignature]"]');
        $this->client->assertSelectorExists('input[name="booking_consent[renterConsent]"]');
        $this->client->assertSelectorExists('button[name="booking_consent[Agree]"][disabled]');
        $this->client->assertSelectorExists('button[name="booking_consent[Agree]"].btn-large.btn-primary');
    }

    public function testInvalidHashReturnsNotFound(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'wrong-hash');
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testInvalidBookingIdReturnsNotFound(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $nonExistingBookingId = (int) $booking->getId() + 999999;
        $path = \sprintf('/booking/%d/renter/%d/%s', $nonExistingBookingId, (int) $renter->getId(), 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testInvalidRenterIdReturnsNotFound(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $nonExistingRenterId = (int) $renter->getId() + 999999;
        $path = \sprintf('/booking/%d/renter/%d/%s', (int) $booking->getId(), $nonExistingRenterId, 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testBookingRenterMismatchReturnsNotFound(): void
    {
        $renter = $this->createRenter('Test Renter');
        $otherRenter = $this->createRenter('Other Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $otherRenter, 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMissingContractReturnsNotFound(): void
    {
        $renter = $this->createRenter('Test Renter');
        $existingContract = $this->entityManager->getRepository(Contract::class)->findOneBy(['purpose' => 'rent']);
        if ($existingContract instanceof Contract) {
            $this->entityManager->remove($existingContract);
            $this->entityManager->flush();
        }
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testEntropyRenterIsHidden(): void
    {
        $renter = $this->ensureEntropyRenter();
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-ENTROPY',
            renterHash: 'hash-entropy',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'hash-entropy');
        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="booking_consent"]');
    }

    public function testPublicItemsRouteWorksForEntropyRenter(): void
    {
        $renter = $this->ensureEntropyRenter();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-ENTROPY',
            renterHash: 'hash-entropy',
        );

        $this->seedClientHome('fi');
        $path = $this->publicPath($booking, 'hash-entropy');
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorNotExists('form[name="booking_consent"]');
    }

    public function testPublicItemsRouteRejectsNonEntropyRenter(): void
    {
        $renter = $this->createRenter('Test Renter');
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->publicPath($booking, 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRenterEntityBehaviors(): void
    {
        $renter = new Renter();
        $renter->setName('Fractal Instruments');
        $renter->setOrganization('Hexagon Intergalactic');
        $renter->setStreetadress('Main Street 1');
        $renter->setZipcode('00100');
        $renter->setCity('Helsinki');
        $renter->setPhone('+358123456');
        $renter->setEmail('renter@example.test');

        $this->entityManager->persist($renter);
        $this->entityManager->flush();

        $this->assertNotNull($renter->getId());
        $this->assertSame('Fractal Instruments', $renter->getName());
        $this->assertSame('Hexagon Intergalactic', $renter->getOrganization());
        $this->assertSame('Main Street 1', $renter->getStreetadress());
        $this->assertSame('00100', $renter->getZipcode());
        $this->assertSame('Helsinki', $renter->getCity());
        $this->assertSame('+358123456', $renter->getPhone());
        $this->assertSame('renter@example.test', $renter->getEmail());
        $this->assertSame('Fractal Instruments / Hexagon Intergalactic', (string) $renter);

        $booking = new Booking();
        $booking->setName('Test booking');
        $booking->setReferenceNumber('REF-777');
        $booking->setRenterHash('hash-777');
        $booking->setRenter($renter);

        $renter->addBooking($booking);
        $this->assertCount(1, $renter->getBookings());

        $collection = new ArrayCollection([$booking]);
        $renter->setBookings($collection);
        $this->assertSame($collection, $renter->getBookings());

        $renter->removeBooking($booking);
        $this->assertCount(0, $renter->getBookings());

        $renter->setOrganization(null);
        $this->assertSame('Fractal Instruments', (string) $renter);
    }

    public function testContractEntityBehaviors(): void
    {
        $contract = new Contract();
        $this->assertSame('purpose', (string) $contract);

        $contract->setPurpose('rent');
        $contract->setContentFi('<div class="contract-fi">Test contract fi</div>');
        $contract->setContentEn('<div class="contract-en">Test contract en</div>');

        $validFrom = new \DateTimeImmutable('2030-01-01 00:00:00');
        $contract->setValidFrom($validFrom);

        $customCreated = new \DateTimeImmutable('2000-01-01 00:00:00');
        $customUpdated = new \DateTimeImmutable('2001-01-01 00:00:00');
        $contract->setCreatedAt($customCreated);
        $contract->setUpdatedAt($customUpdated);

        $this->assertSame('rent', $contract->getPurpose());
        $this->assertSame('<div class="contract-fi">Test contract fi</div>', $contract->getContentFi());
        $this->assertSame('<div class="contract-en">Test contract en</div>', $contract->getContentEn());
        $this->assertSame($validFrom, $contract->getValidFrom());
        $this->assertSame($customCreated, $contract->getCreatedAt());
        $this->assertSame($customUpdated, $contract->getUpdatedAt());
        $this->assertSame('rent', (string) $contract);

        $contract->setCreatedAtValue();
        $this->assertNotEquals($customCreated, $contract->getCreatedAt());
        $this->assertNotEquals($customUpdated, $contract->getUpdatedAt());

        $updatedAfterCreate = $contract->getUpdatedAt();
        $contract->setUpdatedAtValue();
        $this->assertNotEquals($updatedAfterCreate, $contract->getUpdatedAt());

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        $this->assertNotNull($contract->getId());
    }

    public function testHappeningEntityBehaviors(): void
    {
        $happening = new \App\Entity\Happening();
        $happening->setNameFi('Tapahtuma');
        $happening->setNameEn('Happening');
        $happening->setDescriptionFi('Kuvaus FI');
        $happening->setDescriptionEn('Description EN');
        $happening->setSlugFi('tapahtuma-fi');
        $happening->setSlugEn('happening-en');
        $happening->setPaymentInfoFi('Maksuohje FI');
        $happening->setPaymentInfoEn('Payment info EN');
        $happening->setPriceFi('10 EUR');
        $happening->setPriceEn('12 EUR');
        $happening->setType('workshop');
        $happening->setMaxSignUps(42);
        $happening->setNeedsPreliminarySignUp(true);
        $happening->setNeedsPreliminaryPayment(true);
        $happening->setReleaseThisHappeningInEvent(true);
        $happening->setAllowSignUpComments(false);

        $time = new \DateTime('2025-01-01 12:00:00');
        $happening->setTime($time);

        $this->assertSame('Tapahtuma', $happening->getNameFi());
        $this->assertSame('Happening', $happening->getNameEn());
        $this->assertSame('Kuvaus FI', $happening->getDescriptionFi());
        $this->assertSame('Description EN', $happening->getDescriptionEn());
        $this->assertSame('tapahtuma-fi', $happening->getSlugFi());
        $this->assertSame('happening-en', $happening->getSlugEn());
        $this->assertSame('Maksuohje FI', $happening->getPaymentInfoFi());
        $this->assertSame('Payment info EN', $happening->getPaymentInfoEn());
        $this->assertSame('10 EUR', $happening->getPriceFi());
        $this->assertSame('12 EUR', $happening->getPriceEn());
        $this->assertSame('workshop', $happening->getType());
        $this->assertSame(42, $happening->getMaxSignUps());
        $this->assertTrue($happening->isNeedsPreliminarySignUp());
        $this->assertTrue($happening->isNeedsPreliminaryPayment());
        $this->assertTrue($happening->isReleaseThisHappeningInEvent());
        $this->assertFalse($happening->isAllowSignUpComments());
        $this->assertInstanceOf(\DateTimeImmutable::class, $happening->getTime());
        $this->assertSame($time->getTimestamp(), $happening->getTime()->getTimestamp());

        $this->assertSame('Tapahtuma', $happening->getName('fi'));
        $this->assertSame('Happening', $happening->getName('en'));
        $this->assertSame('tapahtuma-fi', $happening->getSlug('fi'));
        $this->assertSame('happening-en', $happening->getSlug('en'));
        $this->assertSame('Kuvaus FI', $happening->getDescription('fi'));
        $this->assertSame('Description EN', $happening->getDescription('en'));
        $this->assertSame('Maksuohje FI', $happening->getPaymentInfo('fi'));
        $this->assertSame('Payment info EN', $happening->getPaymentInfo('en'));
        $this->assertSame('10 EUR', $happening->getPrice('fi'));
        $this->assertSame('12 EUR', $happening->getPrice('en'));
        $this->assertSame('Happening', (string) $happening);

        $member = new \App\Entity\Member();
        $member->setFirstname('Test');
        $member->setLastname('Member');
        $member->setEmail('member@example.test');

        $happening->addOwner($member);
        $this->assertCount(1, $happening->getOwners());
        $happening->removeOwner($member);
        $this->assertCount(0, $happening->getOwners());

        $booking = new \App\Entity\HappeningBooking();
        $happening->addBooking($booking);
        $this->assertCount(1, $happening->getBookings());
        $this->assertSame($happening, $booking->getHappening());
        $happening->removeBooking($booking);
        $this->assertCount(0, $happening->getBookings());

        $event = new \App\Entity\Event();
        $happening->setEvent($event);
        $this->assertSame($event, $happening->getEvent());
        $this->assertNull($happening->getPicture());
        $happening->setPicture(null);
        $this->assertNull($happening->getPicture());

        $this->assertTrue($happening->signUpsAreOpen());
        $happening->setSignUpsOpenUntil(new \DateTimeImmutable('+10 minutes'));
        $this->assertTrue($happening->signUpsAreOpen());
        $happening->setSignUpsOpenUntil(new \DateTimeImmutable('-10 minutes'));
        $this->assertFalse($happening->signUpsAreOpen());

        $happening->setSignUpsOpenUntil(new \DateTime('2030-01-01 00:00:00'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $happening->getSignUpsOpenUntil());
        $happening->setSignUpsOpenUntil(null);
        $this->assertNull($happening->getSignUpsOpenUntil());
    }

    public function testPostingConsentPersistsSignatureAndShowsSuccessFlash(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'hash-abc');
        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $form = $this->client
            ->getCrawler()
            ->filter('form[name="booking_consent"]')
            ->form([
                'booking_consent[renterSignature]' => 'data:image/png;base64,AA==',
                'booking_consent[renterConsent]' => '1',
            ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->client->assertSelectorExists('.alert.alert-success');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Booking::class)->find($booking->getId());
        $this->assertNotNull($reloaded);
        $this->assertTrue($reloaded->getRenterConsent());
        $this->assertSame('data:image/png;base64,AA==', $reloaded->getRenterSignature());
    }

    public function testPostingConsentWithoutSignatureDoesNotPersistAndShowsWarningFlash(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'hash-abc');
        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $form = $this->client
            ->getCrawler()
            ->filter('form[name="booking_consent"]')
            ->form([
                'booking_consent[renterSignature]' => '',
                'booking_consent[renterConsent]' => '1',
            ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->client->assertSelectorExists('.alert.alert-warning');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Booking::class)->find($booking->getId());
        $this->assertNotNull($reloaded);
        $this->assertFalse($reloaded->getRenterConsent());
        $this->assertNull($reloaded->getRenterSignature());
    }

    public function testConsentAlreadyGivenShowsSignedButton(): void
    {
        $renter = $this->createRenter('Test Renter');
        $this->createRentContract();
        $booking = $this->createBookingWithReferenceNumber(
            renter: $renter,
            referenceNumber: 'REF-123',
            renterHash: 'hash-abc',
        );
        $booking->setRenterConsent(true);
        $this->entityManager->flush();

        $this->seedClientHome('fi');
        $path = $this->path($booking, $renter, 'hash-abc');
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('button[name="booking_consent[Signed]"][disabled]');
        $this->client->assertSelectorExists('button[name="booking_consent[Signed]"].btn-secondary.disabled');
    }

    private function path(Booking $booking, Renter $renter, string $hash): string
    {
        $id = $booking->getId();
        $renterId = $renter->getId();
        $this->assertNotNull($id);
        $this->assertNotNull($renterId);

        return \sprintf('/booking/%d/renter/%d/%s', $id, $renterId, $hash);
    }

    private function publicPath(Booking $booking, string $hash): string
    {
        $id = $booking->getId();
        $this->assertNotNull($id);

        return \sprintf('/booking/%d/public/%s', $id, $hash);
    }

    private function createRenter(string $name): Renter
    {
        $renter = new Renter();
        $renter->setName($name);

        $this->entityManager->persist($renter);
        $this->entityManager->flush();

        $this->assertNotNull($renter->getId());
        if (1 === $renter->getId()) {
            $renter = new Renter();
            $renter->setName($name.' #2');
            $this->entityManager->persist($renter);
            $this->entityManager->flush();
            $this->assertNotNull($renter->getId());
        }

        return $renter;
    }

    private function ensureEntropyRenter(): Renter
    {
        $entropyId = Renter::ENTROPY_INTERNAL_ID;
        $existing = $this->entityManager->getRepository(Renter::class)->findOneBy(['id' => $entropyId]);
        if ($existing instanceof Renter) {
            return $existing;
        }

        $this->entityManager->getConnection()->insert('Renter', [
            'id' => $entropyId,
            'name' => 'Entropy',
        ]);

        $this->entityManager->clear();
        $created = $this->entityManager->getRepository(Renter::class)->findOneBy(['id' => $entropyId]);
        $this->assertInstanceOf(Renter::class, $created);

        return $created;
    }

    private function createRentContract(): Contract
    {
        $existing = $this->entityManager->getRepository(Contract::class)->findOneBy(['purpose' => 'rent']);
        if ($existing instanceof Contract) {
            return $existing;
        }

        $contract = new Contract();
        $contract->setPurpose('rent');
        $contract->setContentFi('<div class="contract-fi">Test contract fi</div>');
        $contract->setContentEn('<div class="contract-en">Test contract en</div>');

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    private function createBookingWithReferenceNumber(Renter $renter, string $referenceNumber, string $renterHash): Booking
    {
        $booking = new Booking();
        $booking->setName('Test booking');
        $booking->setReferenceNumber($referenceNumber);
        $booking->setRenterHash($renterHash);
        $booking->setRenter($renter);

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->assertNotNull($booking->getId());

        return $booking;
    }
}
