<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use App\Entity\Email;
use App\Entity\User;
use App\Entity\Member;

final class MemberAdminController extends CRUDController
{
    /* Not in use
    public function makeuserAction($id)
    {
        $object = $this->admin->getSubject();
        if (!$object) {
            $this->addFlash('sonata_flash_error', sprintf('Unable to find the member with id : %s', $id));
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
        if ($object->getUser()) {
            $this->addFlash('sonata_flash_success', sprintf('Member already copied as a User'));
            $object->setUsername($object->getUser()->getUsername());
            $this->admin->update($object);
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        } else {
            $user = new User();
            $passwordEncoder = $this->get('security.password_encoder');
            $pass = bin2hex(openssl_random_pseudo_bytes(6));
            $user->setPassword($passwordEncoder->encodePassword($user,$pass));
            $user->setMember($object);
            $em = $this->get('doctrine.orm.entity_manager');
            $em->persist($user);
            $em->flush();
            $this->admin->update($object);
            //$userEditLink = $this->get('router')->generate('admin_app_user_edit', ['id' => $user->getId()]);
            $this->addFlash('sonata_flash_success',
                sprintf('User created successfully'
            ));
            //$this->addFlash('sonata_flash_error',
            //    sprintf('Please define user groups manually!: <a href="%s">Here</a>', $userEditLink
            //));
        }
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
     */

    /*    public function sendrejectreasonAction($id)
        {
            $object = $this->admin->getSubject();
            // TODO: change to symfony mailer $message = new Message();
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
     */
    public function activememberinfoAction(MailerInterface $mailer): RedirectResponse
    {
        $object = $this->admin->getSubject();
        $em = $this->getDoctrine()->getManager();
        $email = $em->getRepository(Email::class)->findOneBy(['purpose' => 'active_member_info_package']);
        $message = (new TemplatedEmail())
            ->from(new Address('hallitus@entropy.fi', 'Entropyn Hallitus'))
            ->to($object->getEmail())
            ->subject($email->getSubject())
            ->htmlTemplate('emails/member.html.twig')
            ->context(['body' => $email->getBody() ])
        ;
        $mailer->send($message);
        //$this->admin->update($object);
        $this->addFlash('sonata_flash_success', sprintf('Member info package sent to %s', $object->getName()));
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
