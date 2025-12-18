<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\RSVP;
use App\Entity\User;
use App\Form\RSVPType;
use App\Repository\MemberRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\PageBundle\Model\SiteManagerInterface;
use Sonata\PageBundle\Site\SiteSelectorInterface;
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
        SiteSelectorInterface $siteSelector,
        SiteManagerInterface $siteManager,
    ): Response {
        if ($event->getUrl()) {
            if ($event->getExternalUrl()) {
                return new RedirectResponse($event->getUrl());
            }
            $acceptLang = $request->getPreferredLanguage();
            $locale = 'fi' == $acceptLang ? 'fi' : 'en';
            // If we're switching languages, we need to find the correct site first
            $currentSite = $siteSelector->retrieve();
            if ($currentSite->getLocale() !== $locale) {
                // Find the site for the target locale
                $targetSite = $siteManager->findOneBy([
                    'locale' => $locale,
                    'enabled' => true,
                ]);

                if (null !== $targetSite) {
                    // Get the relative path from the target site
                    $relativePath = $targetSite->getRelativePath();

                    // Generate the base URL without locale prefix
                    $baseUrl = $this->generateUrl(
                        'entropy_event_slug',
                        [
                            'year' => $event->getEventDate()->format('Y'),
                            'slug' => $event->getUrl(),
                        ],
                        UrlGeneratorInterface::ABSOLUTE_PATH,
                    );

                    // Combine the site's relative path with the generated URL
                    $url =
                        rtrim($relativePath ?? '', '/').
                        '/'.
                        ltrim($baseUrl, '/');

                    return new RedirectResponse($url);
                }
            }

            // For same locale, generate URL normally
            return $this->redirectToRoute('entropy_event_slug', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
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
        $form = null;
        $user = $this->getUser();
        if ($event->getTicketsEnabled() && $user) {
            \assert($user instanceof User);
            $member = $user->getMember();
            $tickets = $ticketRepo->findBy([
                'event' => $event,
                'owner' => $member,
            ]); // own ticket
        }
        if ($event->getRsvpSystemEnabled() && !$user instanceof UserInterface) {
            $rsvp = new RSVP();
            $form = $this->createForm(RSVPType::class, $rsvp);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $rsvp = $form->getData();
                $repo = $em->getRepository(Member::class);
                \assert($repo instanceof MemberRepository);
                $exists = $repo->findByEmailOrName(
                    $rsvp->getEmail() ?? '',
                    $rsvp->getFirstName() ?? '',
                    $rsvp->getLastName() ?? '',
                );
                if ($exists) {
                    $this->addFlash(
                        'warning',
                        $trans->trans('rsvp.email_in_use'),
                    );
                } else {
                    $rsvp->setEvent($event);
                    try {
                        $em->persist($rsvp);
                        $em->flush();
                        $this->addFlash(
                            'success',
                            $trans->trans('rsvp.rsvpd_successfully'),
                        );
                    } catch (\Exception) {
                        $this->addFlash(
                            'warning',
                            $trans->trans('rsvp.already_rsvpd'),
                        );
                    }
                }
            }
        }
        if (
            !$this->eventTemporalState->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }
        // Determine if logged-in member has already RSVP'd to this event
        $hasRsvpd = false;
        if ($event->getRsvpSystemEnabled() && $user instanceof User) {
            $member = $user->getMember();
            foreach ($member->getRSVPs() as $memberRsvp) {
                if ($memberRsvp->getEvent() === $event) {
                    $hasRsvpd = true;
                    break;
                }
            }
        }
        $template = $event->getTemplate();

        return $this->render($template, [
            'event' => $event,
            'rsvpForm' => $form,
            'tickets' => $tickets ?? null,
            'hasRsvpd' => $hasRsvpd,
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
