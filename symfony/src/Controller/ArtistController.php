<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Artist;
use App\Form\ArtistType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Require ROLE_USER for *every* controller method in this class.
 *
 * @IsGranted("ROLE_USER")
 */
class ArtistController extends AbstractController
{
    public function create(Request $request, Security $security, FormFactoryInterface $formF, TranslatorInterface $trans)
    {
        $member = $security->getUser()->getMember();
        $artist = new Artist();
        $artist->setMember($member);
        $em = $this->getDoctrine()->getManager();
        $form = $formF->create(ArtistType::class, $artist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $artist = $form->getData();
            try {
                $em->persist($artist);
                $em->flush();
                $referer = $request->getSession()->get('referer');
                if($referer){
                    $request->getSession()->remove('referer');
                    return $this->redirect($referer);
                }
                return $this->redirectToRoute('entropy_profile');
            } catch (UniqueConstraintViolationException $e){
                $this->addFlash('warning', $trans->trans('duplicate_artist_found'));
            }
        }
        return $this->render('artist/edit.html.twig', [
            'artist' => $artist,
            'form' => $form->createView()
        ]);

    }
    public function edit(Request $request, Security $security, FormFactoryInterface $formF)
    {
        $artist = $security->getUser()->getMember()->getArtistWithId($request->get('id'));
        $em = $this->getDoctrine()->getManager();
        $form = $formF->create(ArtistType::class, $artist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $artist = $form->getData();
            $em->persist($artist);
            $em->flush();

            return $this->redirectToRoute('entropy_profile');
        }
        return $this->render('artist/edit.html.twig', [
            'artist' => $artist,
            'form' => $form->createView()
        ]);
    }
    public function delete(Artist $artist)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($artist);
        $em->flush();
        return $this->redirectToRoute('entropy_profile');
    }
}
