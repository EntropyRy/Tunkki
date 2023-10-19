<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use App\Entity\Contract;
use App\Entity\Renter;
// Form
use App\Form\BookingConsentType;

class RenterHashController extends Controller
{
    public function indexAction(
        Request $request,
        CmsManagerSelector $cms,
        EntityManagerInterface $em,
        BookingRepository $bRepo
    ): Response {
        $bookingid = $request->get('bookingid');
        $hash = $request->get('hash');
        $renterid = $request->get('renterid');
        if (is_null($bookingid) || is_null($hash) || is_null($renterid)) {
            throw new NotFoundHttpException();
        }
        $renter = $em->getRepository(Renter::class)
            ->findOneBy(['id' => $renterid]);
        if (is_null($renter)) {
            $renter = 0;
        }
        $contract = $em->getRepository(Contract::class)
            ->findOneBy(['purpose' => 'rent']);
        $bookingdata = $bRepo->getBookingData($bookingid, $hash, $renter);
        if (is_array($bookingdata[0])) {
            $object = $bookingdata[1];
            $form = $this->createForm(BookingConsentType::class, $object);
            if ($request->getMethod() == 'POST') {
                $form->handleRequest($request);
                if ($form->isValid() && $form->isSubmitted()) {
                    $booking = $form->getData();
                    if ($booking->getRenterConsent() == true && !is_null($booking->getRenterSignature())) {
                        $em->persist($booking);
                        $em->flush();
                        $this->addFlash('success', 'Allekirjoitettu!');
                        $bookingdata = $bRepo->getBookingData($bookingid, $hash, $renter);
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
                'form' => $form,
                'page' => $page
            ]);
        } else {
            throw new NotFoundHttpException();
        }
    }
}
