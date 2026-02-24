<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Rental;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Rental\RentalRequestForm;
use PHPUnit\Framework\Attributes\DataProvider;

final class RentalRequestFormTest extends LiveComponentTestCase
{
    public function testFormHiddenOnInitialRender(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class);

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('button[data-live-action-param="openForm"]')->count());
        self::assertSame(0, $crawler->filter('form.rental-request-form')->count());
    }

    public function testOpenFormShowsForm(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class);

        $component->call('openForm');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('form.rental-request-form')->count());
        self::assertSame(0, $crawler->filter('button[data-live-action-param="openForm"]')->count());

        // Required fields have the .required class on their labels
        self::assertSame(4, $crawler->filter('form.rental-request-form label.required')->count(),
            'Expected 4 required field labels (bookingDate, renterName, email, phone)');
    }

    public function testCloseFormHidesForm(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class);

        $component->call('openForm');
        $component->call('closeForm');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('button[data-live-action-param="openForm"]')->count());
        self::assertSame(0, $crawler->filter('form.rental-request-form')->count());
        self::assertSame(0, $crawler->filter('.alert')->count());
    }

    public function testValidSubmissionCreatesBookingAndRenter(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class, [], 'en');

        $component->call('openForm');

        $email = 'rental+'.uniqid('', true).'@example.com';
        $response = $component
            ->submitForm([
                'rental_request' => [
                    'eventName' => 'Test Event',
                    'bookingDate' => '2026-06-15',
                    'renterName' => 'Jane Doe',
                    'email' => $email,
                    'phone' => '+358401234567',
                    'organization' => 'Test Org',
                    'streetadress' => 'Test St 1',
                    'zipcode' => '00100',
                    'city' => 'Helsinki',
                    'message' => 'Please reserve for us',
                ],
            ], 'submit')
            ->response();

        self::assertSame(200, $response->getStatusCode());

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-success')->count());

        $this->refreshEntityManager();

        $renter = $this->em()->getRepository(Renter::class)->findOneBy(['email' => $email]);
        self::assertNotNull($renter, 'Renter should be persisted');
        self::assertSame('+358401234567', $renter->getPhone());
        self::assertSame('Jane Doe', $renter->getName());

        $bookings = $this->em()->getRepository(Booking::class)->findBy(['renter' => $renter]);
        self::assertCount(1, $bookings);
        $booking = $bookings[0];
        self::assertSame('Test Event', $booking->getName());
        self::assertSame('2026-06-15', $booking->getBookingDate()->format('Y-m-d'));
        self::assertNotEmpty($booking->getReferenceNumber());
        self::assertNotEmpty($booking->getRenterHash());
    }

    public function testValidSubmissionAutoGeneratesBookingName(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class, [], 'en');

        $component->call('openForm');

        $email = 'rental+'.uniqid('', true).'@example.com';
        $component
            ->submitForm([
                'rental_request' => [
                    'eventName' => '',
                    'bookingDate' => '2026-07-20',
                    'renterName' => 'Auto Name',
                    'email' => $email,
                    'phone' => '+358401234567',
                ],
            ], 'submit');

        $this->refreshEntityManager();

        $renter = $this->em()->getRepository(Renter::class)->findOneBy(['email' => $email]);
        self::assertNotNull($renter);

        $bookings = $this->em()->getRepository(Booking::class)->findBy(['renter' => $renter]);
        self::assertCount(1, $bookings);
        self::assertSame('Rental request - 20.07.2026', $bookings[0]->getName());
    }

    public function testInvalidEmailReturns422(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class, [], 'en');

        $component->call('openForm');

        $this->client()->catchExceptions(true);
        try {
            $response = $component
                ->submitForm([
                    'rental_request' => [
                        'bookingDate' => '2026-06-15',
                        'renterName' => 'Jane Doe',
                        'email' => 'not-an-email',
                        'phone' => '+358401234567',
                    ],
                ], 'submit')
                ->response();
        } finally {
            $this->client()->catchExceptions(false);
        }

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMissingRequiredFieldsReturns422(): void
    {
        $component = $this->mountComponent(RentalRequestForm::class, [], 'en');

        $component->call('openForm');

        $this->client()->catchExceptions(true);
        try {
            $response = $component
                ->submitForm([
                    'rental_request' => [
                        'bookingDate' => '',
                        'renterName' => '',
                        'email' => '',
                        'phone' => '',
                    ],
                ], 'submit')
                ->response();
        } finally {
            $this->client()->catchExceptions(false);
        }

        self::assertSame(422, $response->getStatusCode());
    }

    #[DataProvider('localeProvider')]
    public function testLocaleProvider(string $locale, string $expectedLabel): void
    {
        $component = $this->mountComponent(RentalRequestForm::class, [], $locale);

        $crawler = $component->render()->crawler();
        $button = $crawler->filter('button[data-live-action-param="openForm"]');
        self::assertSame(1, $button->count());
        self::assertSame($expectedLabel, trim($button->text()));
    }

    public static function localeProvider(): array
    {
        return [
            ['fi', 'Tee vuokrauspyyntö'],
            ['en', 'Request a rental'],
        ];
    }
}
