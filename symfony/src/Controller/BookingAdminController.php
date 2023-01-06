<?php

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\BookingRepository;

class BookingAdminController extends CRUDController
{
    public function stuffListAction(BookingRepository $repo): Response
    {
        $object = $this->admin->getSubject();
        $bookingdata = $repo
            ->getBookingData($object->getId(), $object->getRenterHash(), $object->getRenter());
        return $this->renderWithExtraParams('admin/booking/stufflist.html.twig', $bookingdata[0]);
    }
}
