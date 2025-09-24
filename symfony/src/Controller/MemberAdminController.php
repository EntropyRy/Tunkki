<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use App\Entity\Email;
use App\Security\EmailVerifier;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MemberAdminController extends CRUDController
{
    public function activememberinfoAction(
        MailerInterface $mailer,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $object = $this->admin->getSubject();
        $email = $em
            ->getRepository(Email::class)
            ->findOneBy(["purpose" => "active_member_info_package"]);
        $message = new TemplatedEmail()
            ->from(new Address("hallitus@entropy.fi", "Entropyn Hallitus"))
            ->to($object->getEmail())
            ->subject($email->getSubject())
            ->htmlTemplate("emails/member.html.twig")
            ->context(["body" => $email->getBody()]);
        $mailer->send($message);
        //$this->admin->update($object);
        $this->addFlash(
            "sonata_flash_success",
            sprintf("Member info package sent to %s", $object->getName()),
        );
        return new RedirectResponse(
            $this->admin->generateUrl(
                "list",
                $this->admin->getFilterParameters(),
            ),
        );
    }

    public function resendverificationAction(
        EmailVerifier $emailVerifier,
        TranslatorInterface $translator,
    ): RedirectResponse {
        $member = $this->admin->getSubject();
        if (!$member) {
            $this->addFlash("sonata_flash_error", "verify.email.invalid");
            return new RedirectResponse(
                $this->admin->generateUrl(
                    "list",
                    $this->admin->getFilterParameters(),
                ),
            );
        }

        if (
            method_exists($member, "isEmailVerified") &&
            $member->isEmailVerified()
        ) {
            $this->addFlash("sonata_flash_info", "verify.email.already");
            return new RedirectResponse(
                $this->admin->generateUrl(
                    "list",
                    $this->admin->getFilterParameters(),
                ),
            );
        }

        $user = method_exists($member, "getUser") ? $member->getUser() : null;
        if (!$user) {
            $this->addFlash("sonata_flash_error", "verify.email.invalid");
            return new RedirectResponse(
                $this->admin->generateUrl(
                    "list",
                    $this->admin->getFilterParameters(),
                ),
            );
        }

        $verificationEmail = new TemplatedEmail()
            ->from(new Address("webmaster@entropy.fi", "Entropy Webmaster"))
            ->to($member->getEmail())
            ->subject("[Entropy] " . $translator->trans("verify.email.subject"))
            ->htmlTemplate("emails/verify_email.html.twig");

        $emailVerifier->sendEmailConfirmation(
            "app_verify_email",
            $user,
            $verificationEmail,
            ["id" => $user->getId()],
        );

        $this->addFlash(
            "sonata_flash_success",
            sprintf(
                "Verification email resent to %s",
                (string) $member->getEmail(),
            ),
        );

        return new RedirectResponse(
            $this->admin->generateUrl(
                "list",
                $this->admin->getFilterParameters(),
            ),
        );
    }
}
