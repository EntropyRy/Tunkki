<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use App\Entity\Contract;
use App\Entity\Renter;
// Form
use App\Form\BookingConsentType;

class RenterHashController extends Controller
{
    #[Route(
        path: '/booking/{bookingid}/renter/{renterid}/{hash}',
        name: 'entropy_tunkki_booking_hash',
        requirements: [
            'bookingid' => '\d+',
            'renterid' => '\d+'
        ]
    )]
    public function indexAction(
        Request $request,
        CmsManagerSelector $cms,
        EntityManagerInterface $em,
        BookingRepository $bRepo,
    ): Response {
        $bookingid = $request->get('bookingid');
        $hash = $request->get('hash');
        $renterid = $request->get('renterid');
        if (is_null($bookingid) || is_null($hash) || is_null($renterid)) {
            throw new NotFoundHttpException();
        }
        $renter = $em->getRepository(Renter::class)->findOneBy(['id' => $renterid]);
        if (is_null($renter)) { // means that it is For Entropy
            $renter = 0;
        }
        $contract = $em->getRepository(Contract::class)->findOneBy(['purpose' => 'rent']);
        $booking = $bRepo->findOneBy(['id' => $bookingid, 'renterHash' => $hash]);
        if ($booking == null) {
            throw new NotFoundHttpException();
        }
        $form = $this->createForm(BookingConsentType::class, $booking);
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            if ($form->isValid() && $form->isSubmitted()) {
                $booking = $form->getData();
                if ($booking->getRenterConsent() == true && !is_null($booking->getRenterSignature())) {
                    $em->persist($booking);
                    $em->flush();
                    $this->addFlash('success', 'contract.signed');
                } else {
                    $this->addFlash('warning', 'contract.error');
                }
            }
        }
        $page = $cms->retrieve()->getCurrentPage();
        return $this->render('contract.html.twig', [
            'contract' => $contract,
            'renter' => $renter,
            'bookingdata' => $booking->getDataArray(),
            'form' => $form,
            'page' => $page
        ]);
    }
}
