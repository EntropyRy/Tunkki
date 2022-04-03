<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;

// Form
use App\Form\BookingConsentType;


class RenterHashController extends Controller
{
    protected $em;
    public function indexAction(Request $request, CmsManagerSelector $cms)
    {
        $bookingid = $request->get('bookingid');
        $hash = $request->get('hash');
        $renterid = $request->get('renterid');
        if(is_null($bookingid) || is_null($hash) || is_null($renterid)){
            throw new NotFoundHttpException();
        }
        $this->em = $this->getDoctrine()->getManager();
        $renter = $this->em->getRepository('App:Renter')
            ->findOneBy(['id' => $renterid]);
        if(is_null($renter)){
            $renter = 0;
        }
        $contract = $this->em->getRepository('App:Contract')
            ->findOneBy(['purpose' => 'rent']);
        $bookingdata = $this->em->getRepository('App:Booking')
            ->getBookingData($bookingid, $hash, $renter);
        if (is_array($bookingdata[0])){
            $object = $bookingdata[1];
            $form = $this->createForm(BookingConsentType::class, $object);
			if($request->getMethod() == 'POST'){
				$form->handleRequest($request);
                if($form->isValid() && $form->isSubmitted()){

                    $booking = $form->getData();
                    if ($booking->getRenterConsent() == true && !is_null($booking->getRenterSignature())){
                        $this->em->persist($booking);
                        $this->em->flush();
                        $this->addFlash('success', 'Allekirjoitettu!');
                        $bookingdata = $this->em->getRepository('App:Booking')
                            ->getBookingData($bookingid, $hash, $renter);
                    } else {
                        $this->addFlash('warning', 'Allekirjoita uudestaan ja hyväksy ehdot');
                    }
				}
			}
            $page = $cms->retrieve()->getCurrentPage();
            return $this->render('contract.html.twig', [
                'contract' => $contract, 
                'renter' => $renter, 
                'bookingdata' => $bookingdata[0],
                'form' => $form->createView(), 
                'page' => $page
            ]);
        }
        else {
            throw new NotFoundHttpException();
        }
    }
}
