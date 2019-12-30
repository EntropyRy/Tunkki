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
        if(empty($bookingid) || empty($hash) || empty($renterid)){
            throw new NotFoundHttpException();
        }
        $this->em = $this->getDoctrine()->getManager();
        $renter = $this->em->getRepository('App:Renter')
                ->findOneBy(['id' => $renterid]);
        $bookingdata = $this->em->getRepository('App:Booking')
            ->getBookingData($bookingid, $hash, $renter);
        if (is_array($bookingdata[0])){
            $object = $bookingdata[1];
            if($object->getRenterConsent()){
                $class='hidden';
            } else {
                $class='btn btn-large btn-success';
            }
            $form = $this->createForm(BookingConsentType::class, $object);
			if($request->getMethod() == 'POST'){
				$form->handleRequest($request);
				if($form->isValid() && $form->isSubmitted()){
					//$bookingdata = $form->getData();
					$this->em->persist($object);
					$this->em->flush();
				}
			}
            $page = $cms->retrieve()->getCurrentPage();
            return $this->render('stufflist_for_renter.html.twig', [
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
