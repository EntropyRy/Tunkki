<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Artist;
use App\Form\ArtistType;
use App\Helper\Mattermost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArtistController extends AbstractController
{
    public function index(\Symfony\Bundle\SecurityBundle\Security $security): Response
    {
        $member = $security->getUser()->getMember();

        return $this->render('artist/main.html.twig', [
            'member' => $member,
        ]);
    }
    public function create(Request $request, \Symfony\Bundle\SecurityBundle\Security $security, FormFactoryInterface $formF, TranslatorInterface $trans, Mattermost $mm): RedirectResponse|Response
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
                $url_fi = $this->generateUrl('entropy_public_artist.fi', ['name' => $artist->getName()], UrlGeneratorInterface::ABSOLUTE_URL);
                $url_en = $this->generateUrl('entropy_public_artist.en', ['name' => $artist->getName()], UrlGeneratorInterface::ABSOLUTE_URL);
                $text = 'New artist! type: '.$artist->getType().', name: '.$artist->getName().'; **LINKS**: [FI]('. $url_fi .'), [EN]('. $url_en .')' ;
                $mm->SendToMattermost($text, 'yhdistys');
                $referer = $request->getSession()->get('referer');
                if ($referer) {
                    $request->getSession()->remove('referer');
                    return $this->redirect($referer);
                }
                return $this->redirectToRoute('entropy_artist_profile');
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('warning', $trans->trans('duplicate_artist_found'));
            }
        }
        return $this->render('artist/edit.html.twig', [
            'artist' => $artist,
            'form' => $form
        ]);
    }
    public function edit(Request $request, \Symfony\Bundle\SecurityBundle\Security $security, FormFactoryInterface $formF): RedirectResponse|Response
    {
        $artist = $security->getUser()->getMember()->getArtistWithId($request->get('id'));
        $form = $formF->create(ArtistType::class, $artist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $artist = $form->getData();
            $em->persist($artist);
            $em->flush();

            return $this->redirectToRoute('entropy_artist_profile');
        }
        return $this->render('artist/edit.html.twig', [
            'artist' => $artist,
            'form' => $form
        ]);
    }
    public function delete(EntityManagerInterface $em, Artist $artist): RedirectResponse
    {
        $trans = null;
        foreach ($artist->getEventArtistInfos() as $info) {
            $info->removeArtist();
        }
        $em->persist($artist);
        $em->remove($artist);
        $em->flush();
        $this->addFlash('warning', $trans->trans('DELETED'));
        return $this->redirectToRoute('entropy_artist_profile');
    }
}
