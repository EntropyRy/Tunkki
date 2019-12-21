<?php
namespace App\Controller;

use App\Entity\Member;
use App\Form\MemberType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

class MemberFormController extends AbstractController
{
    public function new(Request $request)
    {
        $member = new Member();
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);
        $state=null;
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $name = $em->getRepository(Member::class)->getByName($member->getFirstname(), $member->getLastname());
            $email = $em->getRepository(Member::class)->getByEmail($member->getEmail());
            if(!$name && !$email){
                $em->persist($member);
                $em->flush();
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
}
