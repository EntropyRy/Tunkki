<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use App\Entity\Member;
use App\Entity\NakkiBooking;
use App\Entity\RSVP;
use App\Entity\User;
use App\Form\EventArtistInfoType;
use App\Repository\NakkiBookingRepository;
use App\Service\MattermostNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventSignUpController extends Controller
{
    #[
        Route(
            path: '/{year}/{slug}/nakkikone/{id}/cancel',
            name: 'entropy_event_nakki_cancel',
            requirements: ['year' => "\d+", 'id' => "\d+"],
        ),
    ]
    public function nakkiCancel(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        MattermostNotifierService $mm,
        NakkiBooking $booking,
        NakkiBookingRepository $NakkiBookingR,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if ($booking->getMember() === $member) {
            $booking->setMember(null);
            $em->persist($booking);
            $em->flush();
            $count = $NakkiBookingR->findEventNakkiCount($booking, $event);
            $text =
                '**Nakki reservation cancelled by '.
                $member->getUsername().
                ' from event '.
                $booking.
                '** ('.
                $count.
                ')';
            $mm->sendToMattermost($text, 'nakkikone');
            $this->addFlash('success', 'Nakki cancelled');
        }

        return $this->redirect($request->headers->get('referer'));
        // return $this->redirectToRoute('entropy_event_slug_nakkikone', ['slug' => $slug, 'year' => $year]);
    }

    #[
        Route(
            path: '/{year}/{slug}/nakkikone/{id}/signup',
            name: 'entropy_event_nakki_sign_up',
            requirements: ['year' => "\d+", 'id' => "\d+"],
        ),
    ]
    public function nakkiSignUp(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        MattermostNotifierService $mm,
        NakkiBooking $booking,
        NakkiBookingRepository $NakkiBookingR,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if (!$member->getUsername()) {
            $this->addFlash(
                'danger',
                'Nakki is not reserved! Please define username in you profile',
            );

            return $this->redirect($request->headers->get('referer'));
        }
        if (false == $member->isEmailVerified()) {
            $this->addFlash(
                'danger',
                'Nakki is not reserved! Please verify your email address and send mail to webmaster@entropy.fi',
            );

            return $this->redirect($request->headers->get('referer'));
        }
        if ($event->getNakkikoneEnabled()) {
            if (!$booking->getMember() instanceof Member) {
                if ($event->getRequireNakkiBookingsToBeDifferentTimes()) {
                    $sameTime = $NakkiBookingR->findMemberEventBookingsAtSameTime(
                        $member,
                        $event,
                        $booking->getStartAt(),
                        $booking->getEndAt(),
                    );
                    if ($sameTime) {
                        $this->addFlash(
                            'danger',
                            'You cannot reserve overlapping Nakkis',
                        );

                        return $this->redirect(
                            $request->headers->get('referer'),
                        );
                    }
                }
                $booking->setMember($member);
                $em->persist($booking);
                $em->flush();
                $count = $NakkiBookingR->findEventNakkiCount($booking, $event);
                $text = $text =
                    '**Nakki reservation** '.$booking.' ('.$count.')';
                $mm->sendToMattermost($text, 'nakkikone');
                $this->addFlash('success', 'Nakki reserved');
            } else {
                $this->addFlash(
                    'warning',
                    'Sorry but someone reserved that one already',
                );
            }
        } else {
            $this->addFlash('warning', 'Nakkikone is not enabled');
        }

        return $this->redirect($request->headers->get('referer'));
    }

    #[
        Route(
            path: '/{year}/{slug}/nakkikone',
            name: 'entropy_event_slug_nakkikone',
            requirements: ['year' => "\d+"],
        ),
    ]
    public function nakkikone(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        NakkiBookingRepository $repo,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $selected = $repo->findMemberEventBookings($member, $event);
        if (!$event->getNakkikoneEnabled()) {
            $this->addFlash('warning', 'Nakkikone is not enabled');
        }

        return $this->render('nakkikone.html.twig', [
            'selected' => $selected,
            'event' => $event,
            'nakkis' => $this->getNakkis(
                $event,
                $member,
                $request->getLocale(),
            ),
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/nakkikone/vastuuhenkilo',
                'en' => '/{year}/{slug}/nakkikone/responsible',
            ],
            name: 'entropy_event_responsible',
            requirements: ['year' => "\d+"],
        ),
    ]
    public function responsible(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $gdpr = false;
        $infos = $event->responsibleMemberNakkis($member);
        if (0 === \count($infos)) {
            $gdpr = true;
            $infos = $event->memberNakkis($member);
        }
        $responsibles = $event->getAllNakkiResponsibles($request->getLocale());

        return $this->render('list_nakki_info_for_responsible.html.twig', [
            'gdpr' => $gdpr,
            'event' => $event,
            'infos' => $infos,
            'responsibles' => $responsibles,
        ]);
    }

    protected function getNakkis($event, $member, $locale): array
    {
        $nakkis = [];
        foreach ($event->getNakkiBookings() as $booking) {
            $name = $booking->getNakki()->getDefinition()->getName($locale);
            $duration = $booking
                ->getStartAt()
                ->diff($booking->getEndAt())
                ->format('%h');
            if (
                $booking->getNakki()->getDefinition()->getOnlyForActiveMembers()
            ) {
                if ($member->getIsActiveMember()) {
                    $nakkis = $this->buildNakkiArray(
                        $nakkis,
                        $booking,
                        $name,
                        $duration,
                        $locale,
                    );
                }
            } else {
                $nakkis = $this->buildNakkiArray(
                    $nakkis,
                    $booking,
                    $name,
                    $duration,
                    $locale,
                );
            }
        }

        return $nakkis;
    }

    private function buildNakkiArray(
        array $nakkis,
        $booking,
        $name,
        $duration,
        $locale,
    ): array {
        $event = $booking->getEvent();
        // compare the event start date to the booking start date
        if ($event->getEventDate() > $booking->getStartAt()) {
            $nakkis[$name]['compared_to_event'] = 'nakkikone.build_up';
        } elseif (
            $event->getEventDate() <= $booking->getStartAt()
            && $event->getUntil() >= $booking->getEndAt()
        ) {
            $nakkis[$name]['compared_to_event'] = 'nakkikone.during';
        } else {
            $nakkis[$name]['compared_to_event'] = 'nakkikone.tear_down';
        }

        $nakkis[$name]['description'] = $booking
            ->getNakki()
            ->getDefinition()
            ->getDescription($locale);
        $nakkis[$name]['responsible'] =
            $booking->getNakki()->getResponsible().
            ' ('.
            $booking->getNakki()->getResponsible()?->getUser()->getUsername().
            ')';
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;

        if (null === $booking->getMember()) {
            if (!\array_key_exists('not_reserved', $nakkis[$name])) {
                $nakkis[$name]['not_reserved'] = 1;
            } else {
                ++$nakkis[$name]['not_reserved'];
            }
        }

        return $nakkis;
    }

    #[
        Route(
            path: '/{year}/{slug}/rsvp',
            name: 'entropy_event_rsvp',
            requirements: ['year' => "\d+"],
        ),
    ]
    public function RSVP(
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

        $slug = $event->getUrl();
        $year = $request->get('year');
        /*
        if(empty($slug)){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $event = $this->em->getRepository(Event::class)
                          ->findEventBySlugAndYear($slug, $year);*/
        foreach ($member->getRSVPs() as $rsvp) {
            if ($rsvp->getEvent() == $event) {
                $this->addFlash('warning', $trans->trans('rsvp.already_rsvpd'));

                return $this->redirectToRoute('entropy_event_slug', [
                    'slug' => $slug,
                    'year' => $year,
                ]);
            }
        }
        $rsvp = new RSVP();
        $rsvp->setEvent($event);
        $rsvp->setMember($member);
        $em->persist($rsvp);
        $em->flush();
        $this->addFlash('success', $trans->trans('rsvp.rsvpd_succesfully'));

        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $slug,
            'year' => $year,
        ]);
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
            $this->addFlash('warning', $trans->trans('no_artsit_create_one'));
            $request->getSession()->set('referer', $request->getPathInfo());

            return new RedirectResponse(
                $this->generateUrl('entropy_artist_create'),
            );
        }
        if (!$event->getArtistSignUpNow()) {
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
                    'ask_time' => (bool) $event->getArtistSignUpAskSetLength(),
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
                    $trans->trans('succesfully_signed_up_for_the_party'),
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

        // $page = $cms->retrieve()->getCurrentPage();
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
            || $event->isInPast()
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
