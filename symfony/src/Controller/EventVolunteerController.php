<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\NakkiBooking;
use App\Entity\RSVP;
use App\Entity\User;
use App\Form\RSVPType;
use App\Repository\MemberRepository;
use App\Repository\NakkiBookingRepository;
use App\Repository\RSVPRepository;
use App\Security\Voter\EventNakkiAdminVoter;
use App\Service\MattermostNotifierService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * EventVolunteerController - Handles volunteer (nakki) signups and member RSVPs.
 */
class EventVolunteerController extends AbstractController
{
    #[
        Route(
            path: '/{year}/{slug}/nakkikone/{id}/cancel',
            name: 'entropy_event_nakki_cancel',
            requirements: ['year' => "\d+", 'id' => "\d+"],
        ),
    ]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
    }

    #[
        Route(
            path: '/{year}/{slug}/nakkikone/{id}/signup',
            name: 'entropy_event_nakki_sign_up',
            requirements: ['year' => "\d+", 'id' => "\d+"],
        ),
    ]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
        if (false === $member->isEmailVerified()) {
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
            path: [
                'fi' => '/{year}/{slug}/nakkikone/hallinta',
                'en' => '/{year}/{slug}/nakkikone/admin',
            ],
            name: 'entropy_event_nakki_admin',
            requirements: ['year' => "\d+"],
        ),
    ]
    #[IsGranted(EventNakkiAdminVoter::ATTRIBUTE, 'event')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function nakkiAdmin(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
    ): Response {
        return $this->render('event/nakkikone_admin.html.twig', [
            'event' => $event,
        ]);
    }

    #[
        Route(
            path: '/{year}/{slug}/nakkikone',
            name: 'entropy_event_slug_nakkikone',
            requirements: ['year' => "\d+"],
        ),
    ]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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

    #[
        Route(path: '/{year}/{slug}/rsvp', name: 'entropy_event_rsvp', requirements: ['year' => "\d+"], methods: ['POST']),
    ]
    public function rsvp(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        RSVPRepository $rsvpRepository,
        TranslatorInterface $trans,
        EntityManagerInterface $em,
    ): Response {
        $slug = $event->getUrl();
        $year = $request->attributes->getInt('year');

        if (!$event->getRsvpSystemEnabled()) {
            $this->addFlash('warning', $trans->trans('rsvp.disabled'));

            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $slug,
                'year' => $year,
            ]);
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            $member = $user->getMember();

            if ($rsvpRepository->existsForMemberAndEvent($member, $event)) {
                $this->addFlash(
                    'warning',
                    $trans->trans('rsvp.already_rsvpd'),
                );

                return $this->redirectToRoute('entropy_event_slug', [
                    'slug' => $slug,
                    'year' => $year,
                ]);
            }

            $rsvp = new RSVP();
            $rsvp->setEvent($event);
            $rsvp->setMember($member);

            try {
                $em->persist($rsvp);
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash(
                    'warning',
                    $trans->trans('rsvp.already_rsvpd'),
                );

                return $this->redirectToRoute('entropy_event_slug', [
                    'slug' => $slug,
                    'year' => $year,
                ]);
            }

            $this->addFlash('success', $trans->trans('rsvp.rsvpd_successfully'));

            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $slug,
                'year' => $year,
            ]);
        }

        $rsvp = new RSVP();
        $form = $this->createForm(RSVPType::class, $rsvp);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response('', 422);
        }

        $repo = $em->getRepository(Member::class);
        \assert($repo instanceof MemberRepository);

        $exists = $repo->findByEmailOrName(
            $rsvp->getEmail() ?? '',
            $rsvp->getFirstName() ?? '',
            $rsvp->getLastName() ?? '',
        );

        if ($exists) {
            $this->addFlash('warning', $trans->trans('rsvp.email_in_use'));

            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $slug,
                'year' => $year,
            ]);
        }

        $rsvp->setEvent($event);

        try {
            $em->persist($rsvp);
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash(
                'warning',
                $trans->trans('rsvp.already_rsvpd'),
            );

            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $slug,
                'year' => $year,
            ]);
        }

        $this->addFlash('success', $trans->trans('rsvp.rsvpd_successfully'));

        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $slug,
            'year' => $year,
        ]);
    }

    /**
     * Get nakkis for display in nakkikone (volunteer signup view).
     *
     * This is different from NakkiDisplayService::getNakkiFromGroup()
     * as it includes additional metadata and filtering logic.
     */
    protected function getNakkis(Event $event, Member $member, string $locale): array
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

    /**
     * Build nakki array with additional metadata for volunteer view.
     */
    private function buildNakkiArray(
        array $nakkis,
        $booking,
        string $name,
        string $duration,
        string $locale,
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
}
