<?php

namespace Entropy\TunkkiBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Entropy\TunkkiBundle\Entity\Item;

class ItemAdminController extends Controller
{
    public function cloneAction()
    {
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }
        $clonedObject = new Item;
        //$clonedObject = clone $object;
        $clonedObject->setName($object->getName().' (Clone)');
        $clonedObject->setManufacturer($object->getManufacturer());
        $clonedObject->setModel($object->getModel());
        $clonedObject->setPlaceinstorage($object->getPlaceinstorage());
        $clonedObject->setDescription($object->getDescription());
        $clonedObject->setCommission($object->getCommission());
        $clonedObject->setCommissionPrice($object->getCommissionPrice());
        foreach ($object->getWhoCanRent() as $who){
            $clonedObject->addWhoCanRent($who);
        }
        $clonedObject->setRent($object->getRent());
        $clonedObject->setRentNotice($object->getRentNotice());
        $clonedObject->setForSale($object->getForSale());
        $clonedObject->setToSpareParts($object->getToSpareParts());
        $clonedObject->setNeedsFixing($object->getNeedsFixing());

        $this->admin->create($clonedObject);

        $clonedObject->setCategory($object->getCategory());
        $this->admin->update($clonedObject);

        $this->addFlash('sonata_flash_success', 'Cloned successfully');

        //return new RedirectResponse($this->admin->generateUrl('list'));

        // if you have a filtered list and want to keep your filters after the redirect
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
