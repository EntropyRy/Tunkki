<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Email;
use App\Entity\Member;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends CRUDController<Member>
 */
final class MemberAdminController extends CRUDController
{
    public function activememberinfoAction(
        MailerInterface $mailer,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $subject = $this->admin->getSubject();
        if (!$subject instanceof Member) {
            $url = $this->admin->generateUrl(
                'list',
                $this->admin->getFilterParameters(),
            );

            return new RedirectResponse($url);
        }

        /** @var Email|null $template */
        $template = $em
            ->getRepository(Email::class)
            ->findOneBy(['purpose' => 'active_member_info_package']);

        $emailSubject = $template
            ? (string) $template->getSubject()
            : 'Member information package';
        $emailBody = $template
            ? (string) $template->getBody()
            : 'Information package content is currently unavailable.';

        $message = new TemplatedEmail();
        $message
            ->from(new Address('hallitus@entropy.fi', 'Entropyn Hallitus'))
            ->to((string) $subject->getEmail())
            ->subject($emailSubject)
            ->htmlTemplate('emails/member.html.twig')
            ->context(['body' => $emailBody]);

        $mailer->send($message);

        $this->addFlash(
            'sonata_flash_success',
            \sprintf(
                'Member info package sent to %s',
                (string) $subject->getName(),
            ),
        );

        $url = $this->admin->generateUrl(
            'list',
            $this->admin->getFilterParameters(),
        );

        return new RedirectResponse($url);
    }

    public function resendverificationAction(
        EmailVerifier $emailVerifier,
        TranslatorInterface $translator,
    ): RedirectResponse {
        $member = $this->admin->getSubject();
        if (!$member instanceof Member) {
            $url = $this->admin->generateUrl(
                'list',
                $this->admin->getFilterParameters(),
            );

            return new RedirectResponse($url);
        }

        if ($member->isEmailVerified()) {
            $this->addFlash('sonata_flash_info', 'verify.email.already');

            $url = $this->admin->generateUrl(
                'list',
                $this->admin->getFilterParameters(),
            );

            return new RedirectResponse($url);
        }

        $user = $member->getUser();
        $verificationEmail = new TemplatedEmail();
        $verificationEmail
            ->from(new Address('webmaster@entropy.fi', 'Entropy Webmaster'))
            ->to((string) $member->getEmail())
            ->subject('[Entropy] '.$translator->trans('verify.email.subject'))
            ->htmlTemplate('emails/verify_email.html.twig');

        $emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            $verificationEmail,
            ['id' => $user->getId()],
        );

        $this->addFlash(
            'sonata_flash_success',
            \sprintf(
                'Verification email resent to %s',
                (string) $member->getEmail(),
            ),
        );

        $url = $this->admin->generateUrl(
            'list',
            $this->admin->getFilterParameters(),
        );

        return new RedirectResponse($url);
    }
}
