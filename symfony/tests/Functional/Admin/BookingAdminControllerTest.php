<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\Rental\Booking\BookingAdmin;
use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for BookingAdminController.
 *
 * Tests cover:
 * - stuffListAction: renders stufflist page with booking data
 * - removeSignatureAction: removes renter signature and redirects
 * - Security: access control for admin actions
 */
#[Group('admin')]
#[Group('booking')]
final class BookingAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testStuffListActionRendersStufflistPage(): void
    {
        // Arrange: create super admin user and a booking
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $renter = $this->createRenter('Test Renter');
        $booking = $this->createBooking($renter, 'Test Booking for Stufflist');

        // Act: request the stufflist page
        $stuffListUrl = '/admin/booking/'.$booking->getId().'/stufflist';
        $this->client->request('GET', $stuffListUrl);

        // Assert: page renders successfully
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', 'Test Booking for Stufflist');
    }

    public function testRemoveSignatureActionRemovesSignatureAndRedirects(): void
    {
        // Arrange: create super admin user and a booking with signature
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $renter = $this->createRenter('Signature Test Renter');
        $booking = $this->createBooking($renter, 'Signature Test Booking');

        // Set a signature and consent on the booking
        $booking->setRenterSignature('data:image/png;base64,testSignatureData');
        $booking->setRenterConsent(true);
        $booking->setBookingDate(new \DateTimeImmutable('+1 day')); // Future date to allow removal

        $this->em()->persist($booking);
        $this->em()->flush();

        // Verify signature is set
        self::assertNotNull($booking->getRenterSignature());
        self::assertTrue($booking->getRenterConsent());

        // Act: request the removeSignature action
        $removeSignatureUrl = '/admin/booking/'.$booking->getId().'/remove-signature';
        $this->client->request('GET', $removeSignatureUrl);

        // Assert: redirected to list page
        $response = $this->client->getResponse();
        self::assertTrue(
            $response->isRedirect(),
            'Expected redirect after removing signature, got status: '.$response->getStatusCode()
        );

        // Refresh entity from database
        $this->em()->clear();
        $updatedBooking = $this->em()->getRepository(Booking::class)->find($booking->getId());

        self::assertNotNull($updatedBooking);
        self::assertNull($updatedBooking->getRenterSignature(), 'Signature should be removed');
        self::assertFalse($updatedBooking->getRenterConsent(), 'Consent should be reset to false');
    }

    public function testStuffListActionDeniesAccessToAnonymousUser(): void
    {
        // Arrange: create a booking without logging in
        $renter = $this->createRenter('Anonymous Test Renter');
        $booking = $this->createBooking($renter, 'Anonymous Test Booking');

        // Reset auth to ensure we're anonymous
        $this->resetAuthSession();

        // Act: request stufflist without authentication
        $this->client->request('GET', '/admin/booking/'.$booking->getId().'/stufflist');

        // Assert: redirected to login (302) - anonymous users get redirected
        $response = $this->client->getResponse();
        self::assertSame(
            302,
            $response->getStatusCode(),
            'Anonymous user should be redirected to login'
        );
        self::assertMatchesRegularExpression(
            '#/login(/|$)#',
            $response->headers->get('Location') ?? '',
            'Should redirect to login page'
        );
    }

    public function testRemoveSignatureActionDeniesAccessToAnonymousUser(): void
    {
        // Arrange: create a booking without logging in
        $renter = $this->createRenter('Anonymous Signature Renter');
        $booking = $this->createBooking($renter, 'Anonymous Signature Booking');

        // Reset auth to ensure we're anonymous
        $this->resetAuthSession();

        // Act: request removeSignature without authentication
        $this->client->request('GET', '/admin/booking/'.$booking->getId().'/remove-signature');

        // Assert: redirected to login (302)
        $response = $this->client->getResponse();
        self::assertSame(
            302,
            $response->getStatusCode(),
            'Anonymous user should be redirected to login'
        );
    }

    public function testStuffListActionDeniesAccessToNonAdminUser(): void
    {
        // Arrange: create a regular user (no ROLE_ADMIN)
        $this->loginAsMember();

        $renter = $this->createRenter('Non Admin Test Renter');
        $booking = $this->createBooking($renter, 'Non Admin Test Booking');

        // Act: attempt to access stufflist as non-admin
        $this->client->request('GET', '/admin/booking/'.$booking->getId().'/stufflist');

        // Assert: access denied (403 Forbidden)
        $response = $this->client->getResponse();
        self::assertSame(
            403,
            $response->getStatusCode(),
            'Non-admin user should get 403 Forbidden'
        );
    }

    public function testStuffListActionWithBookingContainingPrices(): void
    {
        // Arrange: create super admin and booking with prices
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $renter = $this->createRenter('Price Test Renter');
        $booking = $this->createBooking($renter, 'Price Test Booking');
        $booking->setActualPrice('150.00');
        $booking->setAccessoryPrice('25.00');

        $this->em()->persist($booking);
        $this->em()->flush();

        // Act: request stufflist page
        $stuffListUrl = '/admin/booking/'.$booking->getId().'/stufflist';
        $this->client->request('GET', $stuffListUrl);

        // Assert: page renders with booking data
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorExists('h1');
        $this->client->assertSelectorTextContains('h1', 'Price Test Booking');
    }

    public function testBookingAdminListPageAccessibleToAdmin(): void
    {
        // Arrange: login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: request the booking list page
        $this->client->request('GET', '/admin/booking/list');

        // Assert: page is accessible
        self::assertResponseIsSuccessful();
    }

    public function testBookingAdminServiceIsConfigured(): void
    {
        // Assert: booking admin service is available
        $admin = $this->getBookingAdmin();
        self::assertInstanceOf(BookingAdmin::class, $admin);
    }

    private function getBookingAdmin(): BookingAdmin
    {
        $admin = static::getContainer()->get('entropy.admin.booking');
        self::assertInstanceOf(BookingAdmin::class, $admin);

        return $admin;
    }

    private function createRenter(string $name): Renter
    {
        $renter = new Renter();
        $renter->setName($name);
        $renter->setEmail(strtolower(str_replace(' ', '_', $name)).'@example.test');

        $this->em()->persist($renter);
        $this->em()->flush();

        return $renter;
    }

    private function createBooking(Renter $renter, string $name): Booking
    {
        $booking = new Booking();
        $booking->setName($name);
        $booking->setRenter($renter);
        $booking->setRenterHash(bin2hex(random_bytes(16)));
        $booking->setReferenceNumber('REF-'.uniqid('', true));
        $booking->setBookingDate(new \DateTimeImmutable());

        $this->em()->persist($booking);
        $this->em()->flush();

        return $booking;
    }
}
