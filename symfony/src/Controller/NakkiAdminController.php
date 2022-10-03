<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Nakki;

final class NakkiAdminController extends CRUDController
{
    protected function preCreate(Request $request, $object): ?Response
    {
        if ($object->getEvent()) {
            $date = new \DateTimeImmutable($object->getEvent()->getEventDate()->format('Y-m-d H:i'));
        } else {
            $date = new \DateTimeImmutable();
        }
        $object->setStartAt($date);
        return null;
    }

    public function cloneAction(): RedirectResponse
    {
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object'));
        }
        $clone = clone $object;
        $this->admin->create($clone);
        $this->addFlash('sonata_flash_success', 'Cloned successfully');
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
