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
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $old = $em->getRepository(Member::class)->getDuplicate($member);
            if(!$old){
                $em->persist($member);
                $em->flush();
            } else {

            } 
        }

        return $this->render('member/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
