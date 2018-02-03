<?php

namespace Entropy\TunkkiBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\HttpFoundation\Request;
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
        $clonedObject->setURL($object->getURL());
        foreach ($object->getWhoCanRent() as $who){
            $clonedObject->addWhoCanRent($who);
        }
        $clonedObject->setRent($object->getRent());
        $clonedObject->setRentNotice($object->getRentNotice());
        $clonedObject->setForSale($object->getForSale());
        $clonedObject->setToSpareParts($object->getToSpareParts());
        $clonedObject->setNeedsFixing($object->getNeedsFixing());
        $clonedObject->setCategory($object->getCategory());

        $this->admin->create($clonedObject);

        $this->addFlash('sonata_flash_success', 'Cloned successfully');

        //return new RedirectResponse($this->admin->generateUrl('list'));

        // if you have a filtered list and want to keep your filters after the redirect
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }

    public function batchActionBatchEditIsRelevant(array $selectedIds, $allEntitiesSelected, Request $request = null)
    {
        $parameterBag = $request->request;
        if ($allEntitiesSelected) {
            return true;
        }
        
        if(count($selectedIds) < 2){
            return "not enough selected";
        }
        else {
            return true;
        }

    }


   /**
     * @param ProxyQueryInterface $selectedModelQuery
     * @param Request             $request
     *
     * @return RedirectResponse
     */
    public function batchActionBatchEdit(ProxyQueryInterface $selectedModelQuery, Request $request = null)
    {
        $this->admin->checkAccess('edit');

        $modelManager = $this->admin->getModelManager();

        $selectedModels = $selectedModelQuery->execute();
        $sourceModel = $selectedModels[0];
        unset($selectedModels[0]);

        try {
            foreach ($selectedModels as $selectedModel) {
                $selectedModel->resetWhoCanRent();
                foreach ($sourceModel->getWhoCanRent() as $who){
                    $selectedModel->addWhoCanRent($who);
                }
                $selectedModel->setDescription($sourceModel->getDescription());
                $selectedModel->setRent($sourceModel->getRent());
                $selectedModel->setRentNotice($sourceModel->getRentNotice());
            }

            $modelManager->update($selectedModel);
        } catch (\Exception $e) {
            $this->addFlash('sonata_flash_error', 'flash_batch_merge_error');

            return new RedirectResponse(
                $this->admin->generateUrl('list', array('filter' => $this->admin->getFilterParameters()))
            );
        }

        $this->addFlash('sonata_flash_success', 'Batch edit success! who can rent, description, rent and rent notice copied! from:'.$sourceModel->getName());

        return new RedirectResponse(
            $this->admin->generateUrl('list', array('filter' => $this->admin->getFilterParameters()))
        );
    }

}
