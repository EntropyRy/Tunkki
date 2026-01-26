<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Entity\User;
use App\Repository\MemberRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Handles verification & resending of verification emails.
 *
 * We support anonymous verification (user does not need to be logged in)
 * by including an ?id= query parameter for the User id in the signed URL.
 *
 * Translation keys used:
 *  - verify.email.invalid
 *  - verify.email.success
 *  - verify.email.resent
 *  - verify.email.already
 */
class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Anonymous email verification endpoint.
     *
     * The signed URL (generated during registration) contains the user id
     * as an extra query parameter (?id=...). We:
     *  1. Fetch the User (or fallback attempt on Member) by id
     *  2. Validate the signature
     *  3. Mark Member::emailVerified = true
     */
    #[Route(
        path: [
            'en' => '/verify/email',
            'fi' => '/vahvista/sahkoposti',
        ],
        name: 'app_verify_email',
    ),]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        MemberRepository $memberRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
    ): RedirectResponse {
        $id = $request->query->get('id');

        if (null === $id) {
            $this->addFlash('danger', 'verify.email.invalid');

            return $this->redirectToRoute('app_login');
        }

        // First: try resolving User
        $user = $userRepository->find($id);
        $member = null;

        if ($user instanceof User) {
            $member = $user->getMember();
        }

        // If no user, attempt a direct Member fetch (legacy / fallback)
        if (!$user instanceof User) {
            $member = $memberRepository->find($id);
            if ($member instanceof Member) {
                $user = $member->getUser();
            }
        }

        if (!$user instanceof User || !$member instanceof Member) {
            $this->addFlash('danger', 'verify.email.invalid');

            return $this->redirectToRoute('app_login');
        }

        // Validate signature & email
        try {
            // Use helper directly (anonymous style)
            $verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                (string) $user->getId(),
                (string) ($user->getEmail() ?? $member->getEmail()),
            );
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('danger', 'verify.email.invalid');

            return $this->redirectToRoute('app_login');
        }

        if (!$member->isEmailVerified()) {
            $member->setEmailVerified(true);
            $this->em->flush();
        }

        $this->addFlash('success', 'verify.email.success');

        // Redirect to login (or profile dashboard if you prefer)
        return $this->redirectToRoute('app_login');
    }

    /**
     * Resend verification email for a logged in (but unverified) user.
     */
    #[Route(
        path: [
            'en' => '/profile/resend-verification',
            'fi' => '/profiili/laheta-vahvistus',
        ],
        name: 'profile_resend_verification',
    ),]
    public function resend(TranslatorInterface $translator): RedirectResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $member = $user->getMember();

        if ($member->isEmailVerified()) {
            $this->addFlash('info', 'verify.email.already');

            return $this->redirectToRoute('profile.'.$member->getLocale());
        }

        // Prepare standalone verification email (no welcome content for resend)
        $email = new TemplatedEmail()
            ->from(new Address('webmaster@entropy.fi', 'Entropy Webmaster'))
            ->to($member->getEmail())
            ->subject('[Entropy] '.$translator->trans('verify.email.subject'))
            ->htmlTemplate('emails/verify_email.html.twig');

        // Pass user + extra param for anonymous validation
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            $email,
            ['id' => $user->getId()],
        );

        $this->addFlash('success', 'verify.email.resent');

        return $this->redirectToRoute('profile.'.$member->getLocale());
    }
}
