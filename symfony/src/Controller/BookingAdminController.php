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
        $booking = $repo->findOneBy(['id' => $object->getId(), 'renterHash' => $object->getRenterHash()]);
        return $this->render('admin/booking/stufflist.html.twig', array_merge($booking->getDataArray(), ['object' => $booking, 'action' => 'show']));
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
