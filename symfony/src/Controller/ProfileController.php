<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Email;
use App\Entity\Member;
use App\Entity\User;
use App\Form\ActiveMemberType;
use App\Form\EPicsPasswordType;
use App\Form\MemberType;
use App\Form\UserPasswordType;
use App\Helper\Barcode;
use App\Helper\ePics;
use App\Repository\EmailRepository;
use App\Repository\MemberRepository;
use App\Security\EmailVerifier;
use App\Service\MattermostNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends AbstractController
{
    #[
        Route(
            path: [
                'en' => '/profile/new',
                'fi' => '/profiili/uusi',
            ],
            name: 'profile_new',
        ),
    ]
    public function newMember(
        Request $request,
        MemberRepository $memberRepo,
        EmailRepository $emailRepo,
        UserPasswordHasherInterface $hasher,
        MattermostNotifierService $mm,
        TranslatorInterface $translator,
        EmailVerifier $emailVerifier,
        Barcode $bc,
        EntityManagerInterface $em,
    ): Response {
        $member = new Member();
        $email_content = null;
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();

            // Ensure locale and code are populated before persistence
            if (null === $member->getLocale()) {
                $member->setLocale($request->getLocale());
            }
            if (null === $member->getCode()) {
                $member->setCode($bc->getCode());
            }

            // Create a fresh User and attach (password read from unmapped sub-form)
            $user = new User();
            $user->setMember($member);
            $member->setUser($user);

            $plain = (string) $form->get('user')->get('plainPassword')->get('first')->getData();
            $user->setPassword(
                $hasher->hashPassword(
                    $user,
                    $plain,
                ),
            );
            $user->setAuthId(bin2hex(openssl_random_pseudo_bytes(10)));

            $em->persist($user);
            $em->persist($member);
            $em->flush();

            // Build and send single merged welcome + verification email
            $email_content = $emailRepo->findOneBy([
                'purpose' => 'member',
            ]);
            $this->announceToMattermost($mm, $member->getName());

            $body = $email_content instanceof Email ? $email_content->getBody() : '';
            $subject =
                ($email_content instanceof Email
                    ? $email_content->getSubject()
                    : $translator->trans('member.welcome.subject')).
                $translator->trans(
                    'member.welcome.subject_verify_suffix',
                );

            $welcomeEmail = new TemplatedEmail();
            $welcomeEmail
                ->from(
                    new Address(
                        'webmaster@entropy.fi',
                        'Entropy Webmaster',
                    ),
                )
                ->to($member->getEmail())
                ->subject($subject)
                ->htmlTemplate('emails/member.html.twig')
                ->context([
                    'body' => $body,
                ]);

            $emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $member->getUser(),
                $welcomeEmail,
                ['id' => $member->getUser()->getId()],
            );

            $this->addFlash('info', 'member.join.added');

            return $this->redirectToRoute('app_login');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->render('member/new.html.twig', [
                'form' => $form,
                'email' => $email_content,
            ], new Response('', 422));
        }

        return $this->render('member/new.html.twig', [
            'form' => $form,
            'email' => $email_content,
        ]);
    }

    protected function announceToMattermost($mm, string $member): void
    {
        $text = '**New Member: '.$member.'**';
        $mm->sendToMattermost($text, 'yhdistys');
    }

    #[
        Route(
            path: [
                'en' => '/dashboard',
                'fi' => '/yleiskatsaus',
            ],
            name: 'dashboard',
        ),
    ]
    public function dashboard(Barcode $bc): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();

        // $barcode = $bc->getBarcode($member);
        return $this->render('profile/dashboard.html.twig', [
            'member' => $member,
            // 'barcode' => $barcode
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/profile',
                'fi' => '/profiili',
            ],
            name: 'profile',
        ),
    ]
    public function index(): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();

        return $this->render('profile/main.html.twig', [
            'member' => $member,
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/profile/edit',
                'fi' => '/profiili/muokkaa',
            ],
            name: 'profile_edit',
        ),
    ]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $form = $this->createForm(MemberType::class, $member, ['include_user' => false, 'edit' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $em->persist($member);
            $em->flush();
            $request->setLocale($member->getLocale());
            $this->addFlash('success', 'profile.member_data_changed');

            return $this->redirectToRoute('profile.'.$member->getLocale());
        }

        return $this->render('profile/edit.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/profile/password',
                'fi' => '/profiili/salasana',
            ],
            name: 'profile_password_edit',
        ),
    ]
    public function password(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $form = $this->createForm(UserPasswordType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            \assert($user instanceof User);
            $plainPassword = $form->get('plainPassword')->getData();

            // Extra safeguard: ensure non-empty string before hashing
            if (!\is_string($plainPassword) || '' === $plainPassword) {
                return $this->render('profile/password.html.twig', [
                    'form' => $form,
                ]);
            }

            $hashed = $hasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashed);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'profile.member_data_changed');

            return $this->redirectToRoute('profile');
        }

        return $this->render('profile/password.html.twig', [
            'form' => $form,
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/profile/apply',
                'fi' => '/profiili/aktiiviksi',
            ],
            name: 'apply_for_active_member',
        ),
    ]
    public function apply(
        Request $request,
        MattermostNotifierService $mm,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if ($member->getIsActiveMember()) {
            $this->addFlash('success', 'profile.you_are_active_member_already');

            return $this->redirectToRoute('profile.'.$member->getLocale());
        }
        $form = $this->createForm(ActiveMemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            if (empty($member->getApplicationDate())) {
                $text = '**Active member application by '.$member.'**';
                $mm->sendToMattermost($text, 'yhdistys');
            }
            $member->setApplicationDate(new \DateTime());
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'profile.application_saved');

            return $this->redirectToRoute('profile.'.$member->getLocale());
        }

        return $this->render('profile/apply.html.twig', [
            'form' => $form,
        ]);
    }

    #[
        Route(
            path: [
                'en' => '/profile/epics/password',
                'fi' => '/profiili/epics/salasana',
            ],
            name: 'profile_epics_password',
        ),
    ]
    public function epicsPassword(
        Request $request,
        ePics $epics,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $resolvedUsername =
            $member->getEpicsUsername() ?:
            $member->getUsername() ?? (string) $member->getId();

        $form = $this->createForm(EPicsPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();

            $success = $epics->createOrUpdateUserPassword(
                $resolvedUsername,
                $plain,
            );

            if ($success) {
                if (!$member->getEpicsUsername()) {
                    $member->setEpicsUsername($resolvedUsername);
                    $em->persist($member);
                    $em->flush();
                }
                $this->addFlash('success', 'epics.password_set');
            } else {
                $this->addFlash('danger', 'epics.password_set_failed');
            }

            return $this->redirectToRoute('profile.'.$member->getLocale());
        }

        return $this->render('profile/epics_password.html.twig', [
            'form' => $form,
            'epics_username' => $resolvedUsername,
        ]);
    }
}
