<?php

declare(strict_types=1);

namespace App\Controller\Rental;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Form\Rental\Booking\RentalRequestType;
use App\Service\Rental\Booking\BookingReferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RentalRequestController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/vuokrauspyynto',
            'en' => '/en/rental-request',
        ],
        name: 'rental_request_submit',
        methods: ['POST'],
    )]
    public function submit(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        BookingReferenceService $bookingReferenceService,
        TranslatorInterface $translator,
        string $bookingNotificationEmail,
        string $bookingNotificationFromEmail,
    ): Response {
        $form = $this->createForm(RentalRequestType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'rental_request.error');

            return $this->redirect($request->headers->get('referer', '/'));
        }

        $data = $form->getData();

        $renter = new Renter();
        $renter->setName($data['renterName']);
        $renter->setEmail($data['email']);
        $renter->setPhone($data['phone']);
        $renter->setOrganization($data['organization'] ?? null);
        $renter->setStreetadress($data['streetadress'] ?? null);
        $renter->setZipcode($data['zipcode'] ?? null);
        $renter->setCity($data['city'] ?? null);
        $entityManager->persist($renter);

        $booking = new Booking();
        $bookingName = empty($data['eventName'])
            ? $translator->trans('rental_request.default_name').' - '.$data['bookingDate']->format('d.m.Y')
            : $data['eventName'];
        $booking->setName($bookingName);
        $booking->setBookingDate($data['bookingDate']);
        $booking->setRenter($renter);
        $entityManager->persist($booking);
        $entityManager->flush();

        $bookingReferenceService->assignReferenceAndHash($booking);
        $entityManager->flush();

        $email = new TemplatedEmail()
            ->from(new Address($bookingNotificationFromEmail, 'Tunkki'))
            ->to($bookingNotificationEmail)
            ->subject('New Rental Request: '.$bookingName)
            ->htmlTemplate('emails/notification.html.twig')
            ->context([
                'booking' => $booking,
                'message' => $data['message'] ?? null,
            ]);

        $mailer->send($email);

        $this->addFlash('success', 'rental_request.success');

        return $this->redirect($request->headers->get('referer', '/'));
    }
}
