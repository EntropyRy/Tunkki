<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\EventTemporalStateService;
use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use App\Entity\Member;
use App\Entity\User;
use App\Form\EventArtistInfoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * EventArtistController - Handles artist applications to events.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventArtistController extends AbstractController
{
    public function __construct(
        private readonly EventTemporalStateService $eventTemporalState,
    ) {
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/artisti/ilmottautuminen',
                'en' => '/{year}/{slug}/artist/signup',
            ],
            name: 'entropy_event_slug_artist_signup',
            requirements: ['year' => "\d+"],
        ),
    ]
    public function artistSignUp(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        TranslatorInterface $trans,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        // Re-attach managed Member and fetch managed Artist choices to avoid detached entities
        if (null !== $member->getId()) {
            $managed = $em
                ->getRepository(Member::class)
                ->find($member->getId());
            if (null !== $managed) {
                $member = $managed;
            }
        }
        // Load Artist choices via repository so Doctrine manages them for the choice field
        try {
            $artistChoices = $em
                ->getRepository(Artist::class)
                ->findBy(['member' => $member]);
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[artistSignUp] failed loading artists: '.
                    $e->getMessage().
                    "\n",
            );
            $artistChoices = [];
        }

        if (0 === \count($artistChoices)) {
            $this->addFlash('warning', $trans->trans('no_artist_create_one'));
            $request->getSession()->set('referer', $request->getPathInfo());

            return new RedirectResponse(
                $this->generateUrl('entropy_artist_create'),
            );
        }
        $canShow = $this->eventTemporalState->canShowSignupLink(
            $event,
            $member,
        );

        if (!$canShow) {
            $this->addFlash('warning', $trans->trans('Not allowed'));

            return new RedirectResponse($this->generateUrl('profile'));
        }
        $artisteventinfo = new EventArtistInfo();
        $artisteventinfo->setEvent($event);
        try {
            $form = $this->createForm(
                EventArtistInfoType::class,
                $artisteventinfo,
                [
                    'artists' => $artistChoices,
                    'ask_time' => $event->getArtistSignUpAskSetLength(),
                ],
            );
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[artistSignUp] form build failed: '.$e->getMessage()."\n",
            );
            @fwrite(
                \STDERR,
                '[artistSignUp] artists.count='.\count($artistChoices)."\n",
            );
            throw $e;
        }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $info = $form->getData();
            $artist = $info->getArtist();
            $i = 1;
            foreach ($event->getEventArtistInfos() as $eventinfo) {
                if ($info->getArtist() == $eventinfo->getArtist()) {
                    if (1 === $i) {
                        $this->addFlash(
                            'warning',
                            $trans->trans('this_artist_signed_up_already'),
                        );
                    }
                    ++$i;
                }
            }
            $artistClone = clone $info->getArtist();
            $artistClone->setMember(null);
            $artistClone->setCopyForArchive(true);
            $artistClone->setName(
                $artistClone->getName().
                    ' for '.
                    $event->getName().
                    ' #'.
                    $i,
            );
            $info->setArtistClone($artistClone);
            $em->persist($artistClone);
            $em->persist($info);
            try {
                $em->flush();
                $this->addFlash(
                    'success',
                    $trans->trans('successfully_signed_up_for_the_party'),
                );

                return new RedirectResponse(
                    $this->generateUrl('entropy_artist_profile'),
                );
            } catch (\Exception) {
                $this->addFlash(
                    'warning',
                    $trans->trans('this_artist_signed_up_already'),
                );
            }
        }

        return $this->render('artist/signup.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[
        Route(
            '/{year}/{slug}/signup/{id}/edit',
            name: 'entropy_event_slug_artist_signup_edit',
            requirements: [
                'year' => "\d+",
                'id' => "\d+",
            ],
        ),
    ]
    public function artistSignUpEdit(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        EventArtistInfo $artisteventinfo,
        TranslatorInterface $trans,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if ($artisteventinfo->getArtist()->getMember() !== $member) {
            $this->addFlash('warning', $trans->trans('Not allowed!'));

            return new RedirectResponse(
                $this->generateUrl('entropy_artist_profile'),
            );
        }
        $form = $this->createForm(
            EventArtistInfoType::class,
            $artisteventinfo,
            [
                'ask_time' => $event->getArtistSignUpAskSetLength(),
                'disable_artist' => true,
            ],
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $info = $form->getData();
            $em->persist($info);
            try {
                $em->flush();
                $this->addFlash(
                    'success',
                    $trans->trans('event.form.sign_up.request_edited'),
                );

                return new RedirectResponse(
                    $this->generateUrl('entropy_artist_profile'),
                );
            } catch (\Exception) {
                $this->addFlash(
                    'warning',
                    $trans->trans('Something went wrong!'),
                );
            }
        }

        return $this->render('artist/signup.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[
        Route(
            '/signup/{id}/delete',
            name: 'entropy_event_slug_artist_signup_delete',
            requirements: [
                'year' => "\d+",
                'id' => "\d+",
            ],
        ),
    ]
    public function artistSignUpDelete(
        EventArtistInfo $artisteventinfo,
        TranslatorInterface $trans,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $event = $artisteventinfo->getEvent();
        if (
            $artisteventinfo->getArtist()->getMember() !== $member
            || $this->eventTemporalState->isInPast($event)
        ) {
            $this->addFlash('warning', $trans->trans('Not allowed!'));

            return new RedirectResponse(
                $this->generateUrl('entropy_artist_profile'),
            );
        }
        $artistClone = $artisteventinfo->getArtistClone();
        $em->remove($artistClone);
        $em->remove($artisteventinfo);
        try {
            $em->flush();
            $this->addFlash(
                'success',
                $trans->trans('event.form.sign_up.request_deleted'),
            );
        } catch (\Exception) {
            $this->addFlash('warning', $trans->trans('Something went wrong!'));
        }

        return new RedirectResponse(
            $this->generateUrl('entropy_artist_profile'),
        );
    }
}
