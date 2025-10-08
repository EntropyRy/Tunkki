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
use App\Repository\TicketRepository;
use App\Service\MattermostNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HappeningController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/{year}/{slug}/tapahtuma/luo',
            'en' => '/{year}/{slug}/happening/create',
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
        MattermostNotifierService $mm,
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
            if (
                $hr->findHappeningByEventSlugAndSlug($event->getUrl(), $happening->getSlugFi())
                || $hr->findHappeningByEventSlugAndSlug($event->getUrl(), $happening->getSlugEn())
            ) {
                $this->addFlash('warning', 'happening.same_name_exits');

                return $this->redirectToRoute('entropy_event_happening_edit', [
                    'slug' => $event->getUrl(),
                    'year' => $event->getEventDate()->format('Y'),
                    'happeningSlug' => $happening->getSlug($request->getLocale()),
                ]);
            } else {
                $hr->save($happening, true);
                $text = '** New Happening: ** '.$happening->getNameEn().' for '.$event->getName();
                $mm->sendToMattermost($text, 'yhdistys');
                $this->addFlash('success', 'happening.created');

                return $this->redirectToRoute('entropy_event_happening_show', [
                    'slug' => $event->getUrl(),
                    'year' => $event->getEventDate()->format('Y'),
                    'happeningSlug' => $happening->getSlug($request->getLocale()),
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
            'fi' => '/{year}/{slug}/tapahtuma/{happeningSlug}/muokkaa',
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
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $event = $happening->getEvent();
        if (false == $happening->getOwners()->contains($member)) {
            $this->addFlash('warning', 'You cannot edit this happening');

            return $this->redirectToRoute('entropy_event_slug', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
            ]);
        }
        $form = $this->createForm(HappeningType::class, $happening);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($happening);
            $em->flush();
            $this->addFlash('success', 'happening.edited');

            return $this->redirectToRoute('entropy_event_happening_show', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'happeningSlug' => $happening->getSlug($request->getLocale()),
            ]);
        }

        return $this->render('happening/edit.html.twig', [
            'form' => $form,
            'event' => $event,
            'happening' => $happening,
        ]);
    }

    #[Route(
        path: [
            'en' => '/{year}/{slug}/happening/{happeningSlug}',
            'fi' => '/{year}/{slug}/tapahtuma/{happeningSlug}',
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
        HappeningRepository $happeningRepository,
        HappeningBookingRepository $HBR,
        TicketRepository $ticketR,
        TranslatorInterface $trans,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $admin = false;
        $event = $happening->getEvent();
        $prevAndNext = $happeningRepository->findPreviousAndNext($happening);
        if ($happening->getOwners()->contains($member)) {
            $admin = true;
        }

        // Hide unreleased happenings from non-owners
        if (!$happening->isReleaseThisHappeningInEvent() && !$admin) {
            throw $this->createNotFoundException();
        }

        $happeningB = $HBR->findMemberBooking($member, $happening);
        if (is_null($happeningB)) {
            $happeningB = new HappeningBooking();
            $happeningB->setHappening($happening);
        }
        $ticket_ref = $ticketR->findMemberTicketReferenceForEvent($member, $event);
        if (is_null($ticket_ref)) {
            $ticket_ref = $trans->trans('happening.ticket_missing');
        }
        $form = $this->createForm(HappeningBookingType::class, $happeningB, ['comments' => $happening->isAllowSignUpComments()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $happeningB->setMember($member);
            $HBR->save($happeningB, true);
            $this->addFlash('success', 'happening.you_have_signed_up');

            return $this->redirectToRoute('entropy_event_happening_show', [
                'slug' => $event->getUrl(),
                'year' => $event->getEventDate()->format('Y'),
                'happeningSlug' => $happening->getSlug($request->getLocale()),
            ]);
        }
        $converter = new GithubFlavoredMarkdownConverter();
        $payment_info = '';
        if (!is_null($happening->getPaymentInfo($request->getLocale()))) {
            $payment_info = $converter->convert($happening->getPaymentInfo($request->getLocale()));
        }

        return $this->render('happening/show.html.twig', [
            'prev' => $prevAndNext[0],
            'next' => $prevAndNext[1] ?? null,
            'event' => $event,
            'happening' => $happening,
            'description' => $converter->convert($happening->getDescription($request->getLocale())),
            'payment_info' => $payment_info,
            'happeningB' => $happeningB,
            'admin' => $admin,
            'form' => $form,
            'ticket_ref' => $ticket_ref,
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
        HappeningBookingRepository $hbr,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $event = $happeningB->getHappening()->getEvent();
        if ($happeningB->getMember() === $member) {
            $happeningB->setMember(null);
            $hbr->remove($happeningB, true);
            $this->addFlash('success', 'happening.reservation_cancelled');
        }

        return $this->redirectToRoute('entropy_event_slug', [
            'slug' => $event->getUrl(),
            'year' => $event->getEventDate()->format('Y'),
        ]);
    }
}
