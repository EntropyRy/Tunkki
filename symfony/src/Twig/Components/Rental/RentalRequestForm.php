<?php

declare(strict_types=1);

namespace App\Twig\Components\Rental;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Form\Rental\Booking\RentalRequestType;
use App\Service\Rental\Booking\BookingReferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Rental:RentalRequestForm')]
final class RentalRequestForm
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $formOpen = false;

    #[LiveProp(writable: true)]
    public ?string $notice = null;

    #[LiveProp(writable: true)]
    public ?string $error = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly BookingReferenceService $bookingReferenceService,
        private readonly TranslatorInterface $translator,
        private readonly string $bookingNotificationEmail,
        private readonly string $bookingNotificationFromEmail,
    ) {
    }

    #[LiveAction]
    public function openForm(): void
    {
        $this->formOpen = true;
        $this->error = null;
        $this->notice = null;
    }

    #[LiveAction]
    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->error = null;
        $this->notice = null;
        $this->resetForm(true);
    }

    #[LiveAction]
    public function submit(): void
    {
        $this->error = null;
        $this->notice = null;

        $this->submitForm();

        $form = $this->getForm();
        if (!$form->isValid()) {
            return;
        }

        $data = $form->getData();

        try {
            $renter = new Renter();
            $renter->setName($data['renterName']);
            $renter->setEmail($data['email']);
            $renter->setPhone($data['phone']);
            $renter->setOrganization($data['organization'] ?? null);
            $renter->setStreetadress($data['streetadress'] ?? null);
            $renter->setZipcode($data['zipcode'] ?? null);
            $renter->setCity($data['city'] ?? null);
            $this->entityManager->persist($renter);

            $bookingDate = $data['bookingDate'];
            if ($bookingDate instanceof \DateTime) {
                $bookingDate = \DateTimeImmutable::createFromMutable($bookingDate);
            }

            $booking = new Booking();
            $bookingName = empty($data['eventName'])
                ? $this->translator->trans('rental_request.default_name').' - '.$bookingDate->format('d.m.Y')
                : $data['eventName'];
            $booking->setName($bookingName);
            $booking->setBookingDate($bookingDate);
            $booking->setRenter($renter);
            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            $this->bookingReferenceService->assignReferenceAndHash($booking);
            $this->entityManager->flush();

            $email = new TemplatedEmail()
                ->from(new Address($this->bookingNotificationFromEmail, 'Tunkki'))
                ->to($this->bookingNotificationEmail)
                ->subject('New Rental Request: '.$bookingName)
                ->htmlTemplate('emails/notification.html.twig')
                ->context([
                    'booking' => $booking,
                    'message' => $data['message'] ?? null,
                ]);

            $this->mailer->send($email);

            $this->notice = $this->translator->trans('rental_request.success');
            $this->formOpen = false;
            $this->resetForm();
        } catch (\Throwable) {
            $this->error = $this->translator->trans('rental_request.error');
        }
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(RentalRequestType::class);
    }
}
