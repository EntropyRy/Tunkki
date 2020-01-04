<?php

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;

class BookingAdminController extends CRUDController
{
    protected $em;
    public function stuffListAction() 
    {
        $object = $this->admin->getSubject();
        $this->em = $this->getDoctrine()->getManager();
        $bookingdata = $this->em->getRepository('App:Booking')
              ->getBookingData($object->getId(), $object->getRenterHash(), $object->getRenter());
        return $this->renderWithExtraParams('admin/booking/stufflist.html.twig', $bookingdata[0]);
    }

}
