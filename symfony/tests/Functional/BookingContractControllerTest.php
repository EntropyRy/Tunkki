<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Booking;
use App\Entity\Contract;
use App\Entity\Renter;
use App\Tests\_Base\FixturesWebTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\BookingContractController
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
