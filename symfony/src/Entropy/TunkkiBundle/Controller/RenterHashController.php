<?php
namespace Entropy\TunkkiBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelectorInterface;

class RenterHashController extends Controller
{
    protected $em;
    public function indexAction(Request $request)
    {
        $bookingid = $request->get('bookingid');
        $hash = $request->get('hash');
        $renterid = $request->get('renterid');
        if(empty($bookingid) || empty($hash) || empty($renterid)){
            throw new NotFoundHttpException();
        }
        $this->em = $this->getDoctrine()->getManager();
        $renter = $this->em->getRepository('EntropyTunkkiBundle:Renter')
                ->findOneBy(['id' => $renterid]);
        $bookingdata = $this->em->getRepository('EntropyTunkkiBundle:Booking')
            ->getBookingData($bookingid, $hash, $renter);
        if (!empty($bookingdata)){
            $cms = $this->container->get("sonata.page.cms.page");
            $page = $cms->getCurrentPage();
            return $this->render('EntropyTunkkiBundle::stufflist_for_renter.html.twig', [
                'renter' => $renter, 
                'bookingdata' => $bookingdata, 
                'page' => $page
            ]);
        }
        else {
            throw new NotFoundHttpException();
        }
    }
}
