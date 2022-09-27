<?php

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Booking;

class BookingAdminController extends CRUDController
{
    protected $em;
    public function stuffListAction(): Response
    {
        $object = $this->admin->getSubject();
        $this->em = $this->getDoctrine()->getManager();
        $bookingdata = $this->em->getRepository(Booking::class)
              ->getBookingData($object->getId(), $object->getRenterHash(), $object->getRenter());
        return $this->renderWithExtraParams('admin/booking/stufflist.html.twig', $bookingdata[0]);
    }
}
