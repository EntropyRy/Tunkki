<?php

declare(strict_types=1);

namespace App\Controller\Rental;

use App\Entity\Contract;
use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\Booking\Renter;
use App\Form\Rental\Booking\BookingConsentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
// Form
use Symfony\Component\Routing\Annotation\Route;

class BookingContractController extends Controller
{
    #[Route(
        path: '/booking/{bookingid}/renter/{renterid}/{hash}',
        name: 'entropy_booking_hash',
        requirements: [
            'bookingid' => '\d+',
            'renterid' => '\d+',
        ]
    )]
    public function indexAction(
        Request $request,
        #[MapEntity(mapping: ['bookingid' => 'id', 'hash' => 'renterHash'])]
        Booking $booking,
        #[MapEntity(mapping: ['renterid' => 'id'])]
        Renter $renter,
        EntityManagerInterface $em,
    ): Response {
        if ($booking->getRenter()?->getId() !== $renter->getId()) {
            throw new NotFoundHttpException();
        }
        $contract = $em->getRepository(Contract::class)->findOneBy([
            'purpose' => Contract::PURPOSES['rent'],
        ]);
        if (null === $contract) {
            throw new NotFoundHttpException();
        }
        $form = $this->createForm(BookingConsentType::class, $booking);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid() && $form->isSubmitted()) {
                $booking = $form->getData();
                $signature = $booking->getRenterSignature();
                $hasSignature = \is_string($signature) && '' !== trim($signature);
                if (true === $booking->getRenterConsent() && $hasSignature) {
                    $em->persist($booking);
                    $em->flush();
                    $this->addFlash('success', 'contract.signed');
                } else {
                    $this->addFlash('warning', 'contract.error');
                }
            }
        }

        return $this->render('contract.html.twig', [
            'contract' => $contract,
            'renter' => $renter,
            'bookingdata' => $booking->getDataArray(),
            'form' => $form,
        ]);
    }

    #[Route(
        path: '/booking/{bookingid}/public/{hash}',
        name: 'entropy_tunkki_booking_public_items',
        requirements: [
            'bookingid' => '\d+',
        ]
    )]
    public function publicItemsAction(
        #[MapEntity(mapping: ['bookingid' => 'id', 'hash' => 'renterHash'])]
        Booking $booking,
    ): Response {
        $renter = $booking->getRenter();
        if (!$renter instanceof Renter || Renter::ENTROPY_INTERNAL_ID !== $renter->getId()) {
            throw new NotFoundHttpException();
        }

        return $this->render('contract.html.twig', [
            'contract' => null,
            'renter' => null,
            'bookingdata' => $booking->getDataArray(),
            'form' => null,
        ]);
    }
}
