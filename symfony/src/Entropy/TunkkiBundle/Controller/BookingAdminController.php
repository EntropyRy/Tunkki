<?php

namespace Entropy\TunkkiBundle\Controller;

use Sonata\AdminBundle\Controller\CRUDController;

class BookingAdminController extends CRUDController
{
    public function stuffListAction() 
    {
		$items = [];
		$packages = [];
		$accessories = [];
		$rent['items'] = 0;
		$rent['packages'] = 0;
		$rent['accessories'] = 0;
        $object = $this->admin->getSubject(); 
        foreach ($object->getItems() as $item){
			$items[]=$item;
			$rent['items']+=$item->getRent();
        }
        foreach ($object->getPackages() as $item){
            $packages[]=$item;
			$rent['packages']+=$item->getRent();
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
        return $this->render('EntropyTunkkiBundle:BookingAdmin:stufflist.html.twig', $data);
    }

}
