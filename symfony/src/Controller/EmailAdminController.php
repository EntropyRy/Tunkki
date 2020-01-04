<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;

final class EmailAdminController extends CRUDController
{
    public function previewAction($id)
    {
        $email = $this->admin->getSubject();
        //$em = $this->getDoctrine()->getManager();
        //$email = $em->getRepository('App:Email')
        //      ->findOneBy('id' => $object->getId());
        return $this->renderWithExtraParams('emails/base.html.twig', ['email' => $email]);
    }
}
