<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\User;
use App\Form\ArtistType;
use App\Helper\Mattermost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArtistController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/profiili/artisti',
            'en' => '/profile/artist',
        ],
        name: 'entropy_artist_profile',
    )]
    public function index(): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();

        return $this->render('artist/main.html.twig', [
            'member' => $member,
        ]);
    }
    #[Route(
        path: [
            'fi' => '/profiili/artisti/uusi',
            'en' => '/profile/artist/create',
        ],
        name: 'entropy_artist_create',
    )]
    public function create(
        Request $request,
        FormFactoryInterface $formF,
        Mattermost $mm,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $artist = new Artist();
        $artist->setMember($member);
        $form = $formF->create(ArtistType::class, $artist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $artist = $form->getData();
            if (!is_null($artist->getPicture())) {
                $em->persist($artist);
                $em->flush();
                $url_fi = $this->generateUrl('entropy_public_artist.fi', ['name' => $artist->getName(), 'id' => $artist->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
                $url_en = $this->generateUrl('entropy_public_artist.en', ['name' => $artist->getName(), 'id' => $artist->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
                $text = 'New artist! type: ' . $artist->getType() . ', name: ' . $artist->getName() . '; **LINKS**: [FI](' . $url_fi . '), [EN](' . $url_en . ')';
                $mm->SendToMattermost($text, 'yhdistys');
                $referer = $request->getSession()->get('referer');
                if ($referer) {
                    $request->getSession()->remove('referer');
                    return $this->redirect($referer);
                }
                $this->addFlash('success', 'edited');
                return $this->redirectToRoute('entropy_artist_profile');
            } else {
                $this->addFlash('warning', 'artist.form.pic_missing');
            }
        }
        return $this->render('artist/edit.html.twig', [
            'artist' => $artist,
            'form' => $form
        ]);
    }
    #[Route(
        path: [
            'fi' => '/profiili/artisti/{id}/muokkaa',
            'en' => '/profile/artist/{id}/edit',
        ],
        name: 'entropy_artist_edit',
        requirements: [
            'id' => '\d+',
        ]
    )]
    public function edit(
        Request $request,
        Artist $artist,
        FormFactoryInterface $formF,
        EntityManagerInterface $em
    ): RedirectResponse|Response {
        $form = $formF->create(ArtistType::class, $artist);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $artist = $form->getData();
            $em->persist($artist);
            $em->flush();
            $this->addFlash('success', 'edited');
            return $this->redirectToRoute('entropy_artist_profile');
        }
        return $this->render('artist/edit.html.twig', [
            'artist' => $artist,
            'form' => $form
        ]);
    }
    #[Route(
        path: [
            'fi' => '/profiili/artisti/{id}/poista',
            'en' => '/profile/artist/{id}/delete',
        ],
        name: 'entropy_artist_delete',
        requirements: [
            'id' => '\d+',
        ]
    )]
    public function delete(EntityManagerInterface $em, Artist $artist): RedirectResponse
    {
        foreach ($artist->getEventArtistInfos() as $info) {
            $info->removeArtist();
        }
        $em->persist($artist);
        $em->remove($artist);
        $em->flush();
        return $this->redirectToRoute('entropy_artist_profile');
    }
    #[Route(
        path: [
            'fi' => '/profiili/artisti/{id}/streamit',
            'en' => '/profile/artist/{id}/streams',
        ],
        name: 'entropy_artist_streams',
        requirements: [
            'id' => '\d+',
        ]
    )]
    public function streams(
        Request $request,
        EntityManagerInterface $em,
        Artist $artist
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        // Check that the member owns the artist
        // If not, redirect to the artist profile
        if ($member->getId() !== $artist->getMember()->getId()) {
            $this->addFlash('warning', 'stream.artist.not_yours');
            return $this->redirectToRoute('entropy_artist_profile');
        }
        return $this->render('artist/streams.html.twig', [
            'artist' => $artist,
        ]);
    }
}
