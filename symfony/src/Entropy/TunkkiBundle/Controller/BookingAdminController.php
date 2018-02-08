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
        $object = $this->admin->getSubject(); 
        foreach ($object->getItems() as $item){
            $items[]=$item;
        }
        foreach ($object->getPackages() as $item){
            $packages[]=$item;
        }
        foreach ($object->getAccessories() as $item){
            $accessories[]=$item;
        }
		$data['name']=$object->getName();
		$data['date']=$object->getBookingDate()->format('j.n.Y');
        $data['items']=$items;
        $data['packages']=$packages;
        $data['accessories']=$accessories;
        return $this->render('EntropyTunkkiBundle:BookingAdmin:stufflist.html.twig', $data);
    }

}
