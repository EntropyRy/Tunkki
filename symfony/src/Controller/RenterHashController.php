<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\Renter;
use App\Form\BookingConsentType;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
// Form
use Symfony\Component\Routing\Annotation\Route;

class RenterHashController extends Controller
{
    #[Route(
        path: '/booking/{bookingid}/renter/{renterid}/{hash}',
        name: 'entropy_tunkki_booking_hash',
        requirements: [
            'bookingid' => '\d+',
            'renterid' => '\d+',
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
        if (\in_array(null, [$bookingid, $hash, $renterid], true)) {
            throw new NotFoundHttpException();
        }
        $renter = $em->getRepository(Renter::class)->findOneBy(['id' => $renterid]);
        if (1 == $renter->getId()) { // means that it is For Entropy
            $renter = null;
        }
        $contract = $em->getRepository(Contract::class)->findOneBy(['purpose' => 'rent']);
        $booking = $bRepo->findOneBy(['id' => $bookingid, 'renterHash' => $hash]);
        if (null == $booking) {
            throw new NotFoundHttpException();
        }
        $form = $this->createForm(BookingConsentType::class, $booking);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid() && $form->isSubmitted()) {
                $booking = $form->getData();
                if (true == $booking->getRenterConsent() && null !== $booking->getRenterSignature()) {
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
            'page' => $page,
        ]);
    }
}
