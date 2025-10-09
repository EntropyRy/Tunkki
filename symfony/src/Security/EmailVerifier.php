<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Service responsible for:
 *  - Generating signed email verification links and sending the email.
 *  - Validating a verification request and marking the related Member as verified.
 *
 * This implementation supports the “anonymous validation” style by allowing
 * extra query parameters (e.g. the user id) to be appended to the signed URL.
 *
 * Typical usage in a controller after persisting the Member & User:
 *
 *  $email = (new TemplatedEmail())
 *      ->to($member->getEmail())
 *      ->subject('...')
 *      ->htmlTemplate('emails/verify_email.html.twig');
 *
 *  $emailVerifier->sendEmailConfirmation(
 *      'app_verify_email',
 *      $member->getUser(),
 *      $email,
 *      ['id' => $member->getUser()->getId()] // optional extra params
 *  );
 */
class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Generate a signed URL and send a verification email.
     *
     * @param string         $verifyEmailRouteName The route name that will handle verification
     * @param User           $user                 The authenticated (or newly created) user object
     * @param TemplatedEmail $email                Pre-configured email (template, subject, etc.)
     * @param array          $extraParams          Extra query params to append (e.g. ['id' => $user->getId()])
     */
    public function sendEmailConfirmation(
        string $verifyEmailRouteName,
        User $user,
        TemplatedEmail $email,
        array $extraParams = [],
    ): void {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            $user->getEmail() ?? '',
            $extraParams,
        );

        $context = $email->getContext();
        $context['signedUrl'] = $signatureComponents->getSignedUrl();
        $context[
            'expiresAtMessageKey'
        ] = $signatureComponents->getExpirationMessageKey();
        $context[
            'expiresAtMessageData'
        ] = $signatureComponents->getExpirationMessageData();

        $email->context($context);

        $this->mailer->send($email);
    }

    /**
     * Handle verification when the user is already authenticated.
     *
     * NOTE: This method sets Member::emailVerified = true (rather than a flag
     * directly on the User) because this project stores the email on Member.
     *
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmationForAuthenticatedUser(
        Request $request,
        User $user,
    ): void {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->getId(),
            $user->getEmail() ?? '',
        );

        $member = $this->resolveMemberFromUser($user);
        if ($member && !$member->isEmailVerified()) {
            $member->setEmailVerified(true);
            $this->em->flush();
        }
    }

    /**
     * Handle verification using an anonymous (id-based) link, where the user
     * might not be logged in. Caller must fetch the user entity first.
     *
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmationAnonymous(
        Request $request,
        User $user,
    ): void {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->getId(),
            $user->getEmail() ?? '',
        );

        $member = $this->resolveMemberFromUser($user);
        if ($member && !$member->isEmailVerified()) {
            $member->setEmailVerified(true);
            $this->em->flush();
        }
    }

    /**
     * Small helper to map a User to its Member (if relationship exists).
     */
    private function resolveMemberFromUser(User $user): Member
    {
        return $user->getMember() ?? null;
    }
}
