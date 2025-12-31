<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Booking;
use App\Entity\Contract;
use App\Entity\Renter;
use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Tests\_Base\FixturesWebTestCase;
use App\Controller\RenterHashController;
use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @covers \App\Controller\RenterHashController
 */
final class RenterHashControllerTest extends FixturesWebTestCase
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
        $this->ensureCmsPageForPath($path);
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
        $this->ensureCmsPageForPath($path);
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
        $this->ensureCmsPageForPath($path);
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
        $this->ensureCmsPageForPath($path);
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
        $this->ensureCmsPageForPath($path);
        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMissingAttributesThrowsNotFound(): void
    {
        $controller = static::getContainer()->get(RenterHashController::class);
        $cms = static::getContainer()->get(CmsManagerSelector::class);
        $repo = static::getContainer()->get(BookingRepository::class);
        $request = Request::create('/booking/');

        $this->expectException(NotFoundHttpException::class);
        $controller->indexAction($request, $cms, $this->entityManager, $repo);
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
        $this->ensureCmsPageForPath($path);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
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
        $this->ensureCmsPageForPath($path);
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
        $this->ensureCmsPageForPath($path);
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
        $existing = $this->entityManager->getRepository(Renter::class)->findOneBy(['id' => 1]);
        if ($existing instanceof Renter) {
            return $existing;
        }

        $this->entityManager->getConnection()->insert('Renter', [
            'id' => 1,
            'name' => 'Entropy',
        ]);

        $this->entityManager->clear();
        $created = $this->entityManager->getRepository(Renter::class)->findOneBy(['id' => 1]);
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

    /**
     * RenterHashController uses CmsManagerSelector->retrieve()->getCurrentPage(),
     * which requires a Sonata Page entity for the requested URL.
     */
    private function ensureCmsPageForPath(string $path): void
    {
        $siteRepo = $this->entityManager->getRepository(SonataPageSite::class);
        $pageRepo = $this->entityManager->getRepository(SonataPagePage::class);

        $site = $siteRepo->findOneBy(['locale' => 'fi']);
        $this->assertInstanceOf(SonataPageSite::class, $site);

        $root = $pageRepo->findOneBy(['site' => $site, 'url' => '/']);
        $this->assertInstanceOf(SonataPagePage::class, $root);

        $existing = $pageRepo->findOneBy(['site' => $site, 'url' => $path]);
        if (!$existing instanceof SonataPagePage) {
            $page = new SonataPagePage();
            $page->setSite($site);
            $page->setParent($root);
            $page->setPosition(50);
            $page->setRouteName('page_slug');
            $page->setName('Test Booking Contract');
            $page->setTitle('Test Booking Contract');
            $page->setSlug('test-booking-contract');
            $page->setUrl($path);
            $page->setEnabled(true);
            $page->setDecorate(true);
            $page->setType('sonata.page.service.default');
            $page->setTemplateCode('onecolumn');
            $page->setRequestMethod('GET|POST|HEAD');

            $this->entityManager->persist($page);
            $this->entityManager->flush();
        }

        $container = static::getContainer();
        if ($container->has('sonata.page.service.create_snapshot')) {
            $createSnapshot = $container->get('sonata.page.service.create_snapshot');
            if (method_exists($createSnapshot, 'createBySite')) {
                $createSnapshot->createBySite($site);
            }
        }
    }
}
