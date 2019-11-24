<?php

declare(strict_types=1);

namespace Entropy\TunkkiBundle\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class MemberAdminController extends CRUDController
{
    public function makeuserAction($id)
    {
        $object = $this->admin->getSubject();
        if (!$object) {
            $this->addFlash('sonata_flash_error', sprintf('Unable to find the member with id : %s', $id));
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
        if ($object->getUser()) {
            $this->addFlash('sonata_flash_success', sprintf('Member already copied as a User'));
            $object->setCopiedAsMember(1);
            $this->admin->update($object);
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
        $userM = $this->get('entropy_tunkki.admin.user')->getUserManager();
        $user = $userM->findUserByEmail($object->getEmail());
        if ($user){ // käyttäjä olemassa
            $this->addFlash('sonata_flash_warning', sprintf('User with this email already exists'));
            $user->setMember($object);
            $userM->updateUser($user);
            $this->addFlash('sonata_flash_success', sprintf('User with this email linked as user'));
        } else {
            $user = $userM->createUser();
            $user->setFirstname($object->getFirstname());
            $user->setLastname($object->getLastname());
            $user->setEmail($object->getEmail());
            $user->setPhone($object->getPhone());
            $user->setUsername($object->getName());
            $user->setEnabled(1);
            $pass = bin2hex(openssl_random_pseudo_bytes(6));
            $user->setPlainPassword($pass);
            $user->setMember($object);
            $userM->updateUser($user);
            $object->setCopiedAsMember(1);
            $this->admin->update($object);
            $this->addFlash('sonata_flash_success', sprintf('User created successfully with password : %s, Please define user groups manually!', $pass));
        }
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
