<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\MemberType;
use App\Form\MemberEditType;
use App\Form\ActiveMemberType;
use App\Entity\Member;
use App\Entity\User;
use App\Form\UserPasswordType;
use App\Helper\Barcode;
use App\Helper\Mattermost;
use App\Repository\MemberRepository;
use App\Repository\EmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileController extends AbstractController
{
    #[Route(path: [
        'en' => '/profile/new',
        'fi' => '/profiili/uusi
    '
    ], name: 'profile_new')]
    public function newMember(
        Request $request,
        MemberRepository $memberRepo,
        EmailRepository $emailRepo,
        UserPasswordHasherInterface $hasher,
        Mattermost $mm,
        MailerInterface $mailer,
        Barcode $bc,
        EntityManagerInterface $em
    ): Response {
        $member = new Member();
        $email_content = null;
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $member = $form->getData();
                $name = $memberRepo->getByName($member->getFirstname(), $member->getLastname());
                $email = $memberRepo->getByEmail($member->getEmail());
                if (!$name && !$email) {
                    $user = $member->getUser();
                    $user->setPassword($hasher->hashPassword($user, $form->get('user')->get('plainPassword')->getData()));
                    $member->setLocale($request->getLocale());
                    $member->setCode($bc->getCode());
                    $user->setAuthId(bin2hex(openssl_random_pseudo_bytes(10)));
                    $em->persist($user);
                    $em->persist($member);
                    $em->flush();

                    $email_content = $emailRepo->findOneBy(['purpose' => 'member']);
                    $this->announceToMattermost($mm, $member);
                    if ($email_content) {
                        $this->sendEmailToMember($email_content, $member, $mailer);
                    }
                    $this->addFlash('info', 'member.join.added');
                    $this->redirectToRoute('app_login');
                } else {
                    $this->addFlash('warning', 'member.join.update');
                }
            } else {
                $this->addFlash('danger', $form->getErrors());
            }
        }
        return $this->render('member/new.html.twig', [
            'form' => $form,
            'email' => $email_content
        ]);
    }
    protected function sendEmailToMember($email_content, $member, $mailer): void
    {
        $email = new TemplatedEmail()
            ->from(new Address('webmaster@entropy.fi', 'Entropy Webmaster'))
            ->to($member->getEmail())
            ->subject($email_content->getSubject())
            ->htmlTemplate('emails/member.html.twig')
            ->context([
                'body' => $email_content->getBody(),
            ]);
        $mailer->send($email);
    }
    protected function announceToMattermost($mm, string $member): void
    {
        $text = '**New Member: ' . $member . '**';
        $mm->SendToMattermost($text, 'yhdistys');
    }
    #[Route(path: [
        'en' => '/dashboard',
        'fi' => '/yleiskatsaus
    '
    ], name: 'dashboard')]
    public function dashboard(Barcode $bc): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        // $barcode = $bc->getBarcode($member);
        return $this->render('profile/dashboard.html.twig', [
            'member' => $member,
            //'barcode' => $barcode
        ]);
    }
    #[Route(path: [
        'en' => '/profile',
        'fi' => '/profiili
    '
    ], name: 'profile')]
    public function index(): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        return $this->render('profile/main.html.twig', [
            'member' => $member,
        ]);
    }
    #[Route(path: [
        'en' => '/profile/edit', 
        'fi' => '/profiili/muokkaa'
    ], name: 'profile_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $form = $this->createForm(MemberEditType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $em->persist($member);
            $em->flush();
            $request->setLocale($member->getLocale());
            $this->addFlash('success', 'profile.member_data_changed');
            return $this->redirectToRoute('profile.' . $member->getLocale());
        }
        return $this->render('profile/edit.html.twig', [
            'member' => $member,
            'form' => $form
        ]);
    }
    #[Route(path: [
        'en' => '/profile/password',
        'fi' => '/profiili/salasana'
    ], name: 'profile_password_edit')]
    public function password(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $user = $this->getUser();
        $form = $this->createForm(UserPasswordType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'profile.member_data_changed');
            return $this->redirectToRoute('profile');
        }
        return $this->render('profile/password.html.twig', [
            'form' => $form
        ]);
    }
    #[Route(path: [
        'en' => '/profile/apply',
        'fi' => '/profiili/aktiiviksi'
    ], name: 'apply_for_active_member')]
    public function apply(
        Request $request,
        Mattermost $mm,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if ($member->getIsActiveMember()) {
            $this->addFlash('success', 'profile.you_are_active_member_already');
            return $this->redirectToRoute('profile.' . $member->getLocale());
        }
        $form = $this->createForm(ActiveMemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            if (empty($member->getApplicationDate())) {
                $text = '**Active member application by ' . $member . '**';
                $mm->SendToMattermost($text, 'yhdistys');
            }
            $member->setApplicationDate(new \DateTime());
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'profile.application_saved');
            return $this->redirectToRoute('profile.' . $member->getLocale());
        }
        return $this->render('profile/apply.html.twig', [
            'form' => $form
        ]);
    }
}
