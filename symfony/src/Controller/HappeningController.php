<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Happening;
use App\Entity\HappeningBooking;
use App\Entity\User;
use App\Form\HappeningBookingType;
use App\Form\HappeningType;
use App\Repository\HappeningBookingRepository;
use App\Repository\HappeningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HappeningController extends AbstractController
{
    #[Route(
        path: [
            'en' => '/{year}/{slug}/happening/create',
            'fi' => '/{year}/{slug}/tapahtuma/luo'
        ],
        name: 'entropy_event_happening_create',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function create(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        HappeningRepository $hr,
        SluggerInterface $slugger,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $happening = new Happening();
        $happening->addOwner($member);
        $happening->setEvent($event);
        $happening->setTime($event->getEventDate());
        $form = $this->createForm(HappeningType::class, $happening);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $happening->setSlugFi($slugger->slug($happening->getNameFi())->lower());
            $happening->setSlugEn($slugger->slug($happening->getNameEn())->lower());
            if (
                $hr->findHappeningByEventSlugAndSlug($event->getUrl(), $happening->getSlugFi()) ||
                $hr->findHappeningByEventSlugAndSlug($event->getUrl(), $happening->getSlugEn())
            ) {
                $this->addFlash('warning', 'Happeing with that name already exists');
                return $this->redirectToRoute('entropy_event_happening_edit', [
                    'slug' => $event->getUrl(),
                    'year' => $event->getEventDate()->format('Y'),
                    'happeningSlug' => $happening->getSlug($request->getLocale())
                ]);
            } else {
                $hr->save($happening, true);
                $this->addFlash('success', 'Created!');
                return $this->redirectToRoute('entropy_event_happening_show', [
                    'slug' => $event->getUrl(),
                    'year' => $event->getEventDate()->format('Y'),
                    'happeningSlug' => $happening->getSlug($request->getLocale())
                ]);
            }
        }
        return $this->render('happening/create.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }
    #[Route(
        path: [
            'en' => '/{year}/{slug}/happening/{happeningSlug}/edit',
            'fi' => '/{year}/{slug}/tapahtuma/{happeningSlug}/muokkaa'
        ],
        name: 'entropy_event_happening_edit',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function edit(
        Request $request,
        #[MapEntity(expr: 'repository.findHappeningByEventSlugAndSlug(slug,happeningSlug)')]
        Happening $happening,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $event = $happening->getEvent();
        if ($happening->getOwners()->contains($member) == false) {
            $this->addFlash('warning', 'You cannot edit this happening');
            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y')
            ]);
        }
        $form = $this->createForm(HappeningType::class, $happening);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $happening->setSlugFi($slugger->slug($happening->getNameFi())->lower());
            $happening->setSlugEn($slugger->slug($happening->getNameEn())->lower());
            $em->persist($happening);
            $em->flush();
            $this->addFlash('success', 'Edited!');
            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y')
            ]);
        }
        return $this->render('happening/edit.html.twig', [
            'form' => $form,
            'event' => $event,
            'happening' => $happening
        ]);
    }
    #[Route(
        path: [
            'en' => '/{year}/{slug}/happening/{happeningSlug}',
            'fi' => '/{year}/{slug}/tapahtuma/{happeningSlug}'
        ],
        name: 'entropy_event_happening_show',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function show(
        Request $request,
        #[MapEntity(expr: 'repository.findHappeningByEventSlugAndSlug(slug,happeningSlug)')]
        Happening $happening,
        HappeningBookingRepository $HBR
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $admin = false;
        $event = $happening->getEvent();
        if ($happening->getOwners()->contains($member)) {
            $admin = true;
        }
        $happeningB = $HBR->findMemberBooking($member, $happening);
        if (is_null($happeningB)) {
            $happeningB = new HappeningBooking();
            $happeningB->setHappening($happening);
        }
        $form = $this->createForm(HappeningBookingType::class, $happeningB);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $happeningB->setMember($member);
            $HBR->save($happeningB, true);
            $this->addFlash('success', 'You have singned up!');
            return $this->redirectToRoute('entropy_event_happening_show', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'happeningSlug' => $happening->getSlug($request->getLocale())
            ]);
        }
        return $this->render('happening/show.html.twig', [
            'event' => $happening->getEvent(),
            'happening' => $happening,
            'happeningB' => $happeningB,
            'admin' => $admin,
            'form' => $form
        ]);
    }
    #[Route(
        '/happening/{id}/remove',
        name: 'entropy_event_happening_remove',
        requirements: [
            'year' => '\d+',
            'id' => '\d+',
        ]
    )]
    public function remove(
        HappeningBooking $happeningB,
        HappeningBookingRepository $hbr
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $event = $happeningB->getHappening()->getEvent();
        if ($happeningB->getMember() == $member) {
            $happeningB->setMember(null);
            $hbr->remove($happeningB, true);
            $this->addFlash('success', 'Reservation cancelled');
        }
        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $event->getUrl(),
            'year' => $event->getEventDate()->format('Y'),
        ]);
    }
}
