<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventController extends Controller
{
    use TargetPathTrait;

    public function __construct(
        private readonly EventTemporalStateService $eventTemporalState,
    ) {
    }

    #[
        Route(
            path: [
                'fi' => '/tapahtuma/{id}',
                'en' => '/event/{id}',
            ],
            name: 'entropy_event',
            requirements: [
                'id' => "\d+",
            ],
        ),
    ]
    public function oneId(
        Request $request,
        Event $event,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        if ($event->getUrl()) {
            if ($event->getExternalUrl()) {
                return new RedirectResponse($event->getUrl());
            }

            $preferred = $request->getPreferredLanguage(['fi', 'en']);
            $targetLocale = 'fi' === $preferred ? 'fi' : 'en';

            $url = $urlGenerator->generate(
                'entropy_event_slug',
                [
                    'year' => $event->getEventDate()->format('Y'),
                    'slug' => $event->getUrl(),
                    '_locale' => $targetLocale,
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            );

            return new RedirectResponse($url);
        } elseif ($event->getExternalUrl()) {
            // if there is no advert for the event redirect to events listing
            return new RedirectResponse($this->generateUrl('_page_alias_events_'.$request->getLocale()));
        }
        $template = $event->getTemplate();

        return $this->render($template, [
            'event' => $event,
        ]);
    }

    #[
        Route(
            path: '/{year}/{slug}',
            name: 'entropy_event_slug',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function oneSlug(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        TranslatorInterface $trans,
        TicketRepository $ticketRepo,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if ($event->getTicketsEnabled() && $user) {
            \assert($user instanceof User);
            $member = $user->getMember();
            $tickets = $ticketRepo->findBy([
                'event' => $event,
                'owner' => $member,
            ]); // own ticket
        }
        if (
            !$this->eventTemporalState->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }
        $template = $event->getTemplate();

        return $this->render($template, [
            'event' => $event,
            'tickets' => $tickets ?? null,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/artistit',
                'en' => '/{year}/{slug}/artists',
            ],
            name: 'entropy_event_artists',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function eventArtists(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        $user = $this->getUser();
        if (
            !$this->eventTemporalState->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }

        return $this->render('event/artists.html.twig', [
            'event' => $event,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/aikataulu',
                'en' => '/{year}/{slug}/timetable',
            ],
            name: 'entropy_event_timetable',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function eventTimetable(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        $user = $this->getUser();
        if (
            !$this->eventTemporalState->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }

        return $this->render('event/timetable.html.twig', [
            'event' => $event,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/paikka',
                'en' => '/{year}/{slug}/location',
            ],
            name: 'entropy_event_location',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function eventLocation(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        $user = $this->getUser();
        if (
            (!$this->eventTemporalState->isPublished($event)
                && !$user instanceof UserInterface)
            || !$event->isLocationPublic()
        ) {
            throw $this->createAccessDeniedException('');
        }

        return $this->render('event/location.html.twig', [
            'event' => $event,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/info',
                'en' => '/{year}/{slug}/about',
            ],
            name: 'entropy_event_info',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function eventInfo(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        $user = $this->getUser();
        if (
            !$this->eventTemporalState->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }

        return $this->render('event/info.html.twig', [
            'event' => $event,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/turvallisempi-tila',
                'en' => '/{year}/{slug}/safer-space',
            ],
            name: 'entropy_event_safer_space',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function eventSaferSpace(
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
    ): Response {
        $user = $this->getUser();
        if (
            !$this->eventTemporalState->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }

        return $this->render('event/safer_space.html.twig', [
            'event' => $event,
        ]);
    }
}
