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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Hashids\Hashids;

class ProfileController extends AbstractController
{
    public function newMember(
        Request $request, 
        FormFactoryInterface $formF, 
        MemberRepository $memberRepo,
        EmailRepository $emailRepo,
        UserPasswordEncoderInterface $encoder,
        Mattermost $mm,
        MailerInterface $mailer
    )
    {
        $member = new Member();
        $email_content = null;
        $form = $formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $member = $form->getData();
                $name = $memberRepo->getByName($member->getFirstname(), $member->getLastname());
                $email = $memberRepo->getByEmail($member->getEmail());
                if(!$name && !$email){
                    $user = $member->getUser();
                    $user->setPassword($encoder->encodePassword($user, $form->get('user')->get('plainPassword')->getData()));
                    $member->setLocale($request->getlocale());
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($user);
                    $em->persist($member);
                    $em->flush();

                    $email_content = $emailRepo->findOneBy(['purpose' => 'member']);
                    $this->announceToMattermost($mm, $member);
                    $this->sendEmailToMember($email_content, $member, $mailer);
                    // TODO: 
                    //$code = $this->addToInfoMailingList($member);
                    $this->addFlash('info', 'member.join.added');
                    $this->redirect('app_login');
                } else {
                    $this->addFlash('warning', 'member.join.update');
                }
            } else {
                $this->addFlash('danger', $form->getErrors());
            }
        }
        return $this->render('member/new.html.twig', [
            'form' => $form->createView(),
            'email' => $email_content
        ]);
    }
    protected function sendEmailToMember($email_content, $member, $mailer)
    {
        $email = (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy Webmaster'))
            ->to($member->getEmail())
            ->subject( $email_content->getSubject() )
            ->htmlTemplate('emails/member.html.twig')
            ->context([
                'email_data' => $email_content,
            ])
            ;
        $mailer->send($email);
    }
    protected function announceToMattermost($mm, $member)
    {
        $text = '**New Member: '.$member.'**';
        $mm->SendToMattermost($text, 'yhdistys');
    }
    /**
     * @IsGranted("ROLE_USER")
     */
    public function dashboard(Request $request, Security $security)
    {
        $member = $security->getUser()->getMember();
        return $this->render('profile/dashboard.html.twig', [
            'member' => $member,
        ]);
    }
    /**
     * @IsGranted("ROLE_USER")
     */
    public function index(Request $request, Security $security)
    {
        $member = $security->getUser()->getMember();
        return $this->render('profile/main.html.twig', [
            'member' => $member,
        ]);
    }
    /**
     * @IsGranted("ROLE_USER")
     */
    public function door(Request $request, Security $security, FormFactoryInterface $formF, Mattermost $mm, ZMQHelper $zmq)
    {
        $member = $security->getUser()->getMember();
        $DoorLog = new DoorLog();
        $DoorLog->setMember($member);
        $em = $this->getDoctrine()->getManager();
        $logs = $em->getRepository(DoorLog::class)->getLatest(20);
        $form = $formF->create(OpenDoorType::class, $DoorLog);
        $now = new \DateTime('now');
        $env = $this->getParameter('kernel.debug') ? 'dev' : 'prod';
        $status = $zmq->send($env.' init: '.$member->getUsername().' '.$now->getTimestamp());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $doorlog = $form->getData();
            $em->persist($doorlog);
            $em->flush();
            $status = $zmq->send($env.' open: '.$member->getUsername().' '.$now->getTimestamp());
            // $this->addFlash('success', 'profile.door.opened');
            $this->addFlash('success', $status);

            $send = true;
            $text = '**Kerde door opened by '.$doorlog->getMember();
            if ($doorlog->getMessage()){
                $text .=' - '. $doorlog->getMessage();
            } else {
                foreach($logs as $log){
                    if (!$log->getMessage() && ($now->getTimestamp() - $log->getCreatedAt()->getTimeStamp() < 60*60*4)){
                        $send = false;
                        break;
                    }
                }
            }
            $text.='**';
            if($send){
                $mm->SendToMattermost($text, 'kerde');
            }
            
            if ($member->getLocale()){
                return $this->redirectToRoute('entropy_profile_door.'. $member->getLocale());
            } else {
                return $this->redirectToRoute('entropy_profile_door.'. $request->getLocale());
            }
        }
        $barcode = $this->getBarcode($member);
        return $this->render('profile/door.html.twig', [
            'form' => $form->createView(),
            'logs' => $logs,
            'member' => $member,
            'status' => $status,
            'barcode' => $barcode
        ]);
    }
    /**
     * @IsGranted("ROLE_USER")
     */
    public function edit(Request $request, Security $security, FormFactoryInterface $formF, UserPasswordEncoderInterface $encoder)
    {
        $member = $security->getUser()->getMember();
        $form = $formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $member = $form->getData();
            $user = $member->getUser();
            $user->setPassword($encoder->encodePassword($user, $form->get('user')->get('plainPassword')->getData()));
            $em->persist($user);
            $em->persist($member);
            $em->flush();
            $request->setLocale($member->getLocale());
            $this->addFlash('success', 'profile.member_data_changed');
            return $this->redirectToRoute('entropy_profile.'. $member->getLocale());
        }
        return $this->render('profile/edit.html.twig', [
            'member' => $member,
            'form' => $form->createView()
        ]);
    }
    /**
     * @IsGranted("ROLE_USER")
     */
    public function apply(Request $request, Security $security, FormFactoryInterface $formF, Mattermost $mm)
    {
        $member = $security->getUser()->getMember();
        if($member->getIsActiveMember()){
            $this->addFlash('success', 'profile.you_are_active_member_already');
            return $this->redirectToRoute('entropy_profile.'. $member->getLocale());
        }
        $form = $formF->create(ActiveMemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $member = $form->getData();
            if(empty($member->getApplicationDate())){
                $text = '**Active member application by '.$member.'**';
                $mm->SendToMattermost($text, 'yhdistys');
            }
            $member->setApplicationDate(new \DateTime());
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'profile.application_saved');
            return $this->redirectToRoute('entropy_profile.'. $member->getLocale());
        }
        return $this->render('profile/apply.html.twig', [
            'form' => $form->createView()
        ]);
    }
    private function getBarcode($member)
    {
        $generator = new BarcodeGeneratorHTML();
        $code = $member->getId().''.$member->getId().''.$member->getUser()->getId();
        $hashids = new Hashids($code,8);
        $code = $hashids->encode($code);
        $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 90);
        return [$code, $barcode];
    }
}
