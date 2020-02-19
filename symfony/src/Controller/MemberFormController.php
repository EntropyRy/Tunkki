<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Entity\Email;
use App\Form\MemberType;
use App\Form\ActiveMemberType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;

class MemberFormController extends AbstractController
{
    public function newMember(Request $request, \Swift_Mailer $mailer)
    {
        $member = new Member();
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);
        $state=null;
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $memberRepo = $em->getRepository(Member::class);
            $name = $memberRepo->getByName($member->getFirstname(), $member->getLastname());
            $email = $memberRepo->getByEmail($member->getEmail());
            if(!$name && !$email){
                $em->persist($member);
                $em->flush();
                $this->sendEmailToMember('member', $member, $em, $mailer);
                $code = $this->addToInfoMailingList($member);
                $state = 'added';

            } else {
                $state = 'update';
            } 
        }

        return $this->render('member/form.html.twig', [
            'state' => $state,
            'form' => $form->createView(),
        ]);
    }
    public function activeMember(Request $request, \Swift_Mailer $mailer)
    {
        $member = new Member();
        $form = $this->createForm(ActiveMemberType::class, $member);
        $form->handleRequest($request);
        $state=null;
        if ($form->isSubmitted() && $form->isValid()) {
            $new = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $memberRepo = $em->getRepository(Member::class);
            $member = $memberRepo->getByName($new->getFirstname(), $new->getLastname());
            if($member){
                if(!$member->getIsActiveMember()){
                    if(is_null($member->getApplication())){
                        $member->setEmail($new->getEmail());
                        $member->setUsername($new->getUsername());
                        $member->setPhone($new->getPhone());
                        $member->setCityOfResidence($new->getCityOfResidence());
                        $member->setApplication($new->getApplication());
                        $member->setApplicationDate(new \DateTime());
                        $em->persist($member);
                    } else {
                        $new->setApplicationDate(new \DateTime());
                        $em->persist($new);
                        $member = $new;
                    }
                    $em->flush();
                    $this->sendEmailToMember('active_member', $member, $em, $mailer);
                    $state = 'added';
                } else {
                    $state = 'update';
                }
            } else {
                $state = 'no';
            }
        }

        return $this->render('member/form.html.twig', [
            'state' => $state,
            'form' => $form->createView(),
        ]);
    }
    protected function sendEmailToMember($purpose, $member, $em, $mailer)
    {
        $email = $em->getRepository(Email::class)->findOneBy(['purpose' => $purpose]);
        $message = new \Swift_Message($email->getSubject());
        $message->setFrom([$this->getParameter('mailer_sender_address')], "Tunkki");
        $message->setTo($member->getEmail());
        $message->setBody(
            $this->renderView(
               'emails/base.html.twig',
                   [
                       'email' => $email,
                   ]
               ),
               'text/html'
            );
        $mailer->send($message);
    }
    public function addToInfoMailingList($member)
    {
        $client = HttpClient::create();
        $response = $client->request('POST', 'https://list.ayy.fi/mailman/subscribe/entropy-tiedotus',[
            'query' => [
                'email' => $member->getEmail(),
                'fullname' => $member->getName()
            ]
        ]);
        return $response->getStatusCode();
    }
}
