<?php

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\BookingRepository;

class BookingAdminController extends CRUDController
{
    public function stuffListAction(BookingRepository $repo): Response
    {
        $object = $this->admin->getSubject();
        $bookingdata = $repo
            ->getBookingData($object->getId(), $object->getRenterHash(), $object->getRenter());
        return $this->renderWithExtraParams('admin/booking/stufflist.html.twig', array_merge($bookingdata[0], ['object' => $bookingdata[1], 'action' => 'show']));
    }
    public function removeSignatureAction(): RedirectResponse
    {
        $booking = $this->admin->getSubject();
        $booking->setRenterSignature(null);
        $booking->setRenterConsent(false);
        $this->admin->update($booking);
        $this->addFlash('sonata_flash_success', 'Signature Removed');
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
