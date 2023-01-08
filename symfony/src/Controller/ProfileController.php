<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\MemberType;
use App\Form\ActiveMemberType;
use App\Entity\Member;
use App\Entity\User;
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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileController extends AbstractController
{
    public function newMember(
        Request $request,
        FormFactoryInterface $formF,
        MemberRepository $memberRepo,
        EmailRepository $emailRepo,
        UserPasswordHasherInterface $hasher,
        Mattermost $mm,
        MailerInterface $mailer,
        EntityManagerInterface $em
    ): Response {
        $member = new Member();
        $email_content = null;
        $form = $formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $member = $form->getData();
                $name = $memberRepo->getByName($member->getFirstname(), $member->getLastname());
                $email = $memberRepo->getByEmail($member->getEmail());
                if (!$name && !$email) {
                    $user = $member->getUser();
                    $user->setPassword($hasher->hashPassword($user, $form->get('user')->get('plainPassword')->getData()));
                    $member->setLocale($request->getlocale());
                    $em->persist($user);
                    $em->persist($member);
                    $em->flush();

                    $email_content = $emailRepo->findOneBy(['purpose' => 'member']);
                    $this->announceToMattermost($mm, $member);
                    if ($email_content) {
                        $this->sendEmailToMember($email_content, $member, $mailer);
                    }
                    // TODO:
                    //$code = $this->addToInfoMailingList($member);
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
        $email = (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy Webmaster'))
            ->to($member->getEmail())
            ->subject($email_content->getSubject())
            ->htmlTemplate('emails/member.html.twig')
            ->context([
                'body' => $email_content,
            ]);
        $mailer->send($email);
    }
    protected function announceToMattermost($mm, $member): void
    {
        $text = '**New Member: ' . $member . '**';
        $mm->SendToMattermost($text, 'yhdistys');
    }
    public function dashboard(Barcode $bc): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $barcode = $bc->getBarcode($member);
        return $this->render('profile/dashboard.html.twig', [
            'member' => $member,
            'barcode' => $barcode
        ]);
    }
    public function index(): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        return $this->render('profile/main.html.twig', [
            'member' => $member,
        ]);
    }
    public function edit(
        Request $request,
        FormFactoryInterface $formF,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $form = $formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $user = $member->getUser();
            $user->setPassword($hasher->hashPassword($user, $form->get('user')->get('plainPassword')->getData()));
            $em->persist($user);
            $em->persist($member);
            $em->flush();
            $request->setLocale($member->getLocale());
            $this->addFlash('success', 'profile.member_data_changed');
            return $this->redirectToRoute('entropy_profile.' . $member->getLocale());
        }
        return $this->render('profile/edit.html.twig', [
            'member' => $member,
            'form' => $form
        ]);
    }
    public function apply(
        Request $request,
        FormFactoryInterface $formF,
        Mattermost $mm,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if ($member->getIsActiveMember()) {
            $this->addFlash('success', 'profile.you_are_active_member_already');
            return $this->redirectToRoute('entropy_profile.' . $member->getLocale());
        }
        $form = $formF->create(ActiveMemberType::class, $member);
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
            return $this->redirectToRoute('entropy_profile.' . $member->getLocale());
        }
        return $this->render('profile/apply.html.twig', [
            'form' => $form
        ]);
    }
}
