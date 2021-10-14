<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;

final class NakkiAdminController extends CRUDController
{
	protected function preCreate(Request $request, $object)
    {
        if($object->getEvent()){
             $date = new \DateTimeImmutable($object->getEvent()->getEventDate()->format('Y-m-d H:i'));
        } else {
             $date = new \DateTimeImmutable();
        }
        $object->setStartAt($date);
    }
}
