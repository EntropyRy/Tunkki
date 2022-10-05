<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use App\Entity\Email;

final class MemberAdminController extends CRUDController
{
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
