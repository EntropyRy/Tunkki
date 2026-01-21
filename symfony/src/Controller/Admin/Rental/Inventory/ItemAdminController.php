<?php

declare(strict_types=1);

namespace App\Controller\Admin\Rental\Inventory;

use App\Entity\Rental\Inventory\Item;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @extends Controller<Item>
 */
class ItemAdminController extends Controller
{
    public function cloneAction(): RedirectResponse
    {
        $object = $this->admin->getSubject();

        if (null == $object) {
            throw new NotFoundHttpException('unable to find the object');
        }
        $clonedObject = new Item();
        // $clonedObject = clone $object;
        $clonedObject->setName($object->getName().' (Clone)');
        $clonedObject->setManufacturer($object->getManufacturer());
        $clonedObject->setModel($object->getModel());
        $clonedObject->setPlaceinstorage($object->getPlaceinstorage());
        $clonedObject->setDescription($object->getDescription());
        $clonedObject->setCommission($object->getCommission());
        $clonedObject->setPurchasePrice($object->getPurchasePrice());
        $clonedObject->setURL($object->getURL());
        foreach ($object->getWhoCanRent() as $who) {
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

        // return new RedirectResponse($this->admin->generateUrl('list'));

        // if you have a filtered list and want to keep your filters after the redirect
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }

    public function batchActionBatchEditIsRelevant(array $selectedIds, $allEntitiesSelected, ?Request $request = null): true|string
    {
        if ($allEntitiesSelected) {
            return true;
        }

        if (\count($selectedIds) < 2) {
            return 'not enough selected';
        } else {
            return true;
        }
    }

    public function batchActionBatchEdit(ProxyQueryInterface $selectedModelQuery): RedirectResponse
    {
        $this->admin->checkAccess('edit');

        $modelManager = $this->admin->getModelManager();

        // Convert Paginator/iterable to array for indexed access
        $selectedModels = iterator_to_array($selectedModelQuery->execute());
        if (\count($selectedModels) < 2) {
            $this->addFlash('sonata_flash_error', 'At least 2 items must be selected for batch edit');

            return new RedirectResponse(
                $this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()])
            );
        }

        $sourceModel = array_shift($selectedModels);

        try {
            foreach ($selectedModels as $selectedModel) {
                $selectedModel->resetWhoCanRent();
                foreach ($sourceModel->getWhoCanRent() as $who) {
                    $selectedModel->addWhoCanRent($who);
                }
                $selectedModel->setDescription($sourceModel->getDescription());
                $selectedModel->setRent($sourceModel->getRent());
                $selectedModel->setRentNotice($sourceModel->getRentNotice());
                $modelManager->update($selectedModel);
            }
        } catch (\Exception) {
            $this->addFlash('sonata_flash_error', 'flash_batch_merge_error');

            return new RedirectResponse(
                $this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()])
            );
        }

        $this->addFlash('sonata_flash_success', 'Batch edit success! who can rent, description, rent and rent notice copied! from:'.$sourceModel->getName());

        return new RedirectResponse(
            $this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()])
        );
    }
}
