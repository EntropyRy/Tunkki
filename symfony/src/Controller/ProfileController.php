<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\MemberType;
use App\Form\ActiveMemberType;
use App\Form\OpenDoorType;
use App\Entity\DoorLog;
use App\Entity\Member;
use App\Helper\Mattermost;
use App\Helper\ZMQHelper;
use App\Repository\MemberRepository;
use App\Repository\EmailRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Hashids\Hashids;

class ProfileController extends AbstractController
{
    public function newMember(
        Request $request,
        FormFactoryInterface $formF,
        MemberRepository $memberRepo,
        EmailRepository $emailRepo,
        UserPasswordHasherInterface $hasher,
        Mattermost $mm,
        MailerInterface $mailer
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
                    $em = $this->getDoctrine()->getManager();
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
    public function dashboard(\Symfony\Bundle\SecurityBundle\Security $security): Response
    {
        $member = $security->getUser()->getMember();
        $barcode = $this->getBarcode($member);
        return $this->render('profile/dashboard.html.twig', [
            'member' => $member,
            'barcode' => $barcode
        ]);
    }
    public function index(\Symfony\Bundle\SecurityBundle\Security $security): Response
    {
        $member = $security->getUser()->getMember();
        return $this->render('profile/main.html.twig', [
            'member' => $member,
        ]);
    }
    public function door(Request $request, \Symfony\Bundle\SecurityBundle\Security $security, FormFactoryInterface $formF, Mattermost $mm, ZMQHelper $zmq): RedirectResponse|Response
    {
        $member = $security->getUser()->getMember();
        $DoorLog = new DoorLog();
        $DoorLog->setMember($member);
        $em = $this->getDoctrine()->getManager();
        $since = new \DateTime('now-1day');
        if ($request->get('since')) {
            //$datestring = strtotime($request->get('since'));
            $since = new \DateTime($request->get('since'));
        }
        $logs = $em->getRepository(DoorLog::class)->getSince($since);
        $form = $formF->create(OpenDoorType::class, $DoorLog);
        $now = new \DateTime('now');
        $env = $this->getParameter('kernel.debug') ? 'dev' : 'prod';
        $status = $zmq->send($env . ' init: ' . $member->getUsername() . ' ' . $now->getTimestamp());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $doorlog = $form->getData();
            $em->persist($doorlog);
            $em->flush();
            $status = $zmq->send($env . ' open: ' . $member->getUsername() . ' ' . $now->getTimestamp());
            // $this->addFlash('success', 'profile.door.opened');
            $this->addFlash('success', $status);

            $send = true;
            $text = '**Kerde door opened by ' . $doorlog->getMember();
            if ($doorlog->getMessage()) {
                $text .= ' - ' . $doorlog->getMessage();
            } else {
                foreach ($logs as $log) {
                    if (!$log->getMessage() && ($now->getTimestamp() - $log->getCreatedAt()->getTimeStamp() < 60 * 60 * 4)) {
                        $send = false;
                        break;
                    }
                }
            }
            $text .= '**';
            if ($send) {
                $mm->SendToMattermost($text, 'kerde');
            }

            if ($member->getLocale()) {
                return $this->redirectToRoute('entropy_profile_door.' . $member->getLocale());
            } else {
                return $this->redirectToRoute('entropy_profile_door.' . $request->getLocale());
            }
        }
        $barcode = $this->getBarcode($member);
        return $this->render('profile/door.html.twig', [
            'form' => $form,
            'logs' => $logs,
            'member' => $member,
            'status' => $status,
            'barcode' => $barcode
        ]);
    }
    public function edit(Request $request, \Symfony\Bundle\SecurityBundle\Security $security, FormFactoryInterface $formF, UserPasswordHasherInterface $hasher): RedirectResponse|Response
    {
        $member = $security->getUser()->getMember();
        $form = $formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
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
    public function apply(Request $request, \Symfony\Bundle\SecurityBundle\Security $security, FormFactoryInterface $formF, Mattermost $mm): RedirectResponse|Response
    {
        $member = $security->getUser()->getMember();
        if ($member->getIsActiveMember()) {
            $this->addFlash('success', 'profile.you_are_active_member_already');
            return $this->redirectToRoute('entropy_profile.' . $member->getLocale());
        }
        $form = $formF->create(ActiveMemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
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
    private function getBarcode($member): array
    {
        $generator = new BarcodeGeneratorHTML();
        $code = $member->getId() . '' . $member->getId() . '' . $member->getUser()->getId();
        $hashids = new Hashids($code, 8);
        $code = $hashids->encode($code);
        $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 90);
        return [$code, $barcode];
    }
}
