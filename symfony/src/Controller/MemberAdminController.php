<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Email;

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
            $object->setCopiedAsUser(1);
            $object->setUsername($object->getUser()->getUsername());
            $this->admin->update($object);
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
        $userM = $this->get('entropy_tunkki.admin.user')->getUserManager();
        $user = $userM->findUserByEmail($object->getEmail());
        if ($user){ // käyttäjä olemassa
            $this->addFlash('sonata_flash_error', sprintf('User with this email already exists'));
            $user->setMember($object);
            $userM->updateUser($user);
            $object->setCopiedAsUser(1);
            $object->setUsername($user->getUsername());
            $this->admin->update($object);
            $this->addFlash('sonata_flash_success', sprintf('User with this email linked as user'));
        } else {
            $user = $userM->createUser();
            $user->setFirstname($object->getFirstname());
            $user->setLastname($object->getLastname());
            $user->setEmail($object->getEmail());
            $user->setPhone($object->getPhone());
            $user->setUsername($object->getUsername());
            $user->setEnabled(1);
            $pass = bin2hex(openssl_random_pseudo_bytes(6));
            $user->setPlainPassword($pass);
            $user->setMember($object);
            $userM->updateUser($user);
            $object->setCopiedAsUser(1);
            $this->admin->update($object);
            $userEditLink = $this->get('router')->generate('admin_app_user_edit', ['id' => $user->getId()]);
            $this->addFlash('sonata_flash_success', 
                sprintf('User created successfully with password : %s', $pass
            ));
            $this->addFlash('sonata_flash_error', 
                sprintf('Please define user groups manually!: <a href="%s">Here</a>', $userEditLink
            ));
        }
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }

    public function sendrejectreasonAction($id)
    {
        $object = $this->admin->getSubject();
        $message = new \Swift_Message();
        $message->setFrom(['hallitus@entropy.fi'], "Entropyn Hallitus");
        $message->setTo($object->getEmail());
        $message->setSubject("[Entropy] Hakumuksesi hylättiin / Your application was rejected" );
        $message->setBody(
            $this->renderView(
                'emails/member.html.twig',
                    [
                        'user' => $object,
                        'email' => ['addLoginLinksToFooter'=>false],
                    ]
                ),
                'text/html'
        );
        $this->get('mailer')->send($message);
        $object->setRejectReasonSent(1);
        $this->admin->update($object);
        $this->addFlash('sonata_flash_success', sprintf('Reject reason sent to %s', $object->getName()));
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        
    }
    public function activememberinfoAction($id)
    {
        $object = $this->admin->getSubject();
        $em = $this->getDoctrine()->getManager();
        $email = $em->getRepository(Email::class)->findOneBy(['purpose' => 'active_member_info_package']);
        $message = new \Swift_Message();
        $message->setFrom(['hallitus@entropy.fi'], "Entropyn Hallitus");
        $message->setTo($object->getEmail());
        $message->setSubject($email->getSubject());
        $message->setBody(
            $this->renderView(
                'emails/member.html.twig',
                    [
                        'email' => $email,
                    ]
                ),
                'text/html'
        );
        $this->get('mailer')->send($message);
        //$this->admin->update($object);
        $this->addFlash('sonata_flash_success', sprintf('Member info package sent to %s', $object->getName()));
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        
    }
}
