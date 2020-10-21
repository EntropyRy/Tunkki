<?php
declare(strict_types=1);

namespace App\Controller;

//use App\Entity\Member;
//use App\Entity\Email;
use App\Form\MemberType;
//use App\Form\ActiveMemberType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
//use Symfony\Component\Form\Extension\Core\Type\DateType;
//use Symfony\Component\Form\Extension\Core\Type\SubmitType;
//use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Require ROLE_USER for *every* controller method in this class.
 *
 * @IsGranted("ROLE_USER")
 */
class ProfileController extends AbstractController
{
    public function index(Request $request, Security $security)
    {
        $member = $security->getUser()->getMember();
        return $this->render('profile/main.html.twig', [
            'member' => $member,
        ]);
    }
    public function edit(Request $request, Security $security, FormFactoryInterface $formF, UserPasswordEncoderInterface $encoder)
    {
        $member = $security->getUser()->getMember();
        $form = $formF->create(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $member = $form->getData();
            $user = $member->getUser();
            if (!is_null($user->getPassword())){
                $user->setPassword($encoder->encodePassword($user, $user->getPassword()));
                $em->persist($user);
            }
            $em->persist($member);
            $em->flush();

            return $this->redirectToRoute('entropy_profile');
        }
        return $this->render('profile/edit.html.twig', [
            'member' => $member,
            'form' => $form->createView()
        ]);
    }
}
