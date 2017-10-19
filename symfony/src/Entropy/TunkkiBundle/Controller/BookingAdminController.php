<?php

namespace Entropy\TunkkiBundle\Controller;

use Sonata\AdminBundle\Controller\CRUDController;

class BookingAdminController extends CRUDController
{
    public function stuffListAction() 
    { 
        $object = $this->admin->getSubject(); 
        foreach ($object->getItems() as $item){
            $items[]=$item;
        }
        foreach ($object->getPakages() as $item){
            $pakages[]=$item;
        }
        $data['items']=$items;
        $data['pakages']=$pakages;
        return $this->render('EntropyTunkkiBundle:BookingAdmin:stufflist.html.twig', $data);
    }

}
