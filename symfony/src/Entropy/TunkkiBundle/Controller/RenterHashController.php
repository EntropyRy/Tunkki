<?php
namespace Entropy\TunkkiBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $this->em = $this->container->get('doctrine.orm.entity_manager');
        $renter = $this->em->getRepository('EntropyTunkkiBundle:Renter')
                ->findOneBy(['id' => $renterid]);
        $booking = $this->em->getRepository('EntropyTunkkiBundle:Booking')
            ->findOneBy(['id' => $bookingid, 'renterHash' => $hash]);
        if ( !empty($booking) and $booking->getRenter() == $renter){
            $object = $booking;
        $items = [];
        $packages = [];
        $accessories = [];
        $rent['items'] = 0;
        $compensation['items'] = 0;
        $rent['packages'] = 0;
        $rent['accessories'] = 0;
        foreach ($object->getItems() as $item){
            $items[]=$item;
            $rent['items']+=$item->getPrice();
            $compensation['items']+=$item->getCompensationPrice();
        }
        foreach ($object->getPackages() as $item){
            $packages[]=$item;
        }
        foreach ($object->getAccessories() as $item){
            $accessories[]=$item;
            $rent['accessories']+=$item->getName()->getPrice()*$item->getCount();
        }
        $rent['total'] = $rent['items'] + $rent['packages']; //+ $rent['accessories'];
        $rent['actualTotal']=$object->getActualPrice();

        $data['name']=$object->getName();
        $data['date']=$object->getBookingDate()->format('j.n.Y');
        $data['items']=$items;
        $data['packages']=$packages;
        $data['accessories']=$accessories;
        $data['rent']=$rent;

        return $this->render('EntropyTunkkiBundle::stufflist.html.twig', $data);
        }
        else {
            throw new NotFoundHttpException();
        }
    }
}
