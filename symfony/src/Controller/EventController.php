<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\EventPublicationDecider;
use App\Entity\Cart;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\RSVP;
use App\Entity\User;
use App\Form\CartType;
use App\Form\RSVPType;
use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Repository\MemberRepository;
use App\Repository\NakkiBookingRepository;
use App\Repository\TicketRepository;
use App\Service\StripeService;
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
        private readonly EventPublicationDecider $publicationDecider,
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
        // DEBUG BLOCK (guarded by TEST_EVENT_DEBUG) — remove once issue resolved
        if (getenv('TEST_EVENT_DEBUG')) {
            try {
                $isPub = $this->publicationDecider->isPublished($event);
                $userObj = $this->getUser();
                $who =
                    $userObj instanceof UserInterface
                        ? 'auth:'.$userObj::class
                        : 'anon';
                $pubDate = $event->getPublishDate()?->format('c') ?? 'null';
                $flag = var_export(
                    $this->publicationDecider->isPublished($event),
                    true,
                );
                @fwrite(
                    \STDERR,
                    "[oneSlug] event id={$event->getId()} url={$event->getUrl()} publishedFlag={$flag} publishDate={$pubDate} decider=".
                        ($isPub ? 'PUBLISHED' : 'NOT_PUBLISHED').
                        " user={$who}".
                        \PHP_EOL,
                );
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[oneSlug] debug failed: '.$e->getMessage().\PHP_EOL,
                );
            }
        }
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
                    $rsvp->getEmail(),
                    $rsvp->getFirstName(),
                    $rsvp->getLastName(),
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
                            $trans->trans('rsvp.rsvpd_succesfully'),
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
            !$this->publicationDecider->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            if (getenv('TEST_EVENT_DEBUG')) {
                @fwrite(
                    \STDERR,
                    "[oneSlug] denying anonymous (not published)\n",
                );
            }
            throw $this->createAccessDeniedException('');
        }
        $template = $event->getTemplate();

        return $this->render($template, [
            'event' => $event,
            'rsvpForm' => $form,
            'tickets' => $tickets ?? null,
        ]);
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/kauppa',
                'en' => '/{year}/{slug}/shop',
            ],
            name: 'entropy_event_shop',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function eventShop(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        CartRepository $cartR,
        CheckoutRepository $checkoutR,
        NakkiBookingRepository $nakkirepo,
        TicketRepository $ticketRepo,
    ): Response {
        if (false === $event->ticketPresaleEnabled()) {
            throw $this->createAccessDeniedException('');
        }
        $selected = [];
        $nakkis = [];
        $hasNakki = false;
        $email = null;
        $user = $this->getUser();
        if (
            (!$this->publicationDecider->isPublished($event)
                && !$user instanceof UserInterface)
            || (!$user instanceof UserInterface
                && $event->isNakkiRequiredForTicketReservation())
        ) {
            throw $this->createAccessDeniedException('');
        }
        if (null != $user) {
            \assert($user instanceof User);
            $email = $user->getEmail();
            $member = $user->getMember();
            $selected = $nakkirepo->findMemberEventBookings($member, $event);
            $nakkis = $this->getNakkiFromGroup(
                $event,
                $member,
                $selected,
                $request->getLocale(),
            );
            $hasNakki = [] !== (array) $selected;
        }
        $session = $request->getSession();
        $cart = new Cart();
        $cartId = $session->get('cart');
        if (null != $cartId) {
            $cart = $cartR->findOneBy(['id' => $cartId]);
            if (null == $cart) {
                $cart = new Cart();
            }
        }
        if (null == $cart->getEmail()) {
            $cart->setEmail($email);
        }
        $products = $event->getProducts();
        $max = $checkoutR->findProductQuantitiesInOngoingCheckouts();
        // check that user does not have the product already
        // if user has the product, remove it from the list
        foreach ($products as $key => $product) {
            // if there can be only one ticket per user, check that user does not have the ticket already
            $minus = \array_key_exists($product->getId(), $max)
                ? $max[$product->getId()]
                : 0;
            if (
                1 == $product->getHowManyOneCanBuyAtOneTime()
                && $product->getMax($minus) >= 1
                && $product->isTicket()
            ) {
                foreach (
                    $ticketRepo->findTicketsByEmailAndEvent($email, $event) as $ticket
                ) {
                    if (
                        $ticket->getStripeProductId() == $product->getStripeId()
                    ) {
                        unset($products[$key]);
                    }
                }
            }
        }
        $cart->setProducts($products);
        $form = $this->createForm(CartType::class, $cart);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $cart = $form->getData();
            $cartR->save($cart, true);
            $session->set('cart', $cart->getId());

            return $this->redirectToRoute('event_stripe_checkouts', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
        }
        // if use clicks on the login button then redirect them back to this page
        $this->saveTargetPath($session, 'main', $request->getUri());

        return $this->render('event/shop.html.twig', [
            'selected' => $selected,
            'nakkis' => $nakkis,
            'hasNakki' => $hasNakki,
            'nakkiRequired' => $event->isNakkiRequiredForTicketReservation(),
            'event' => $event,
            'form' => $form,
            'inCheckouts' => $max,
        ]);
    }

    protected function getNakkiFromGroup(
        $event,
        $member,
        $selected,
        $locale,
    ): array {
        $nakkis = [];
        foreach ($event->getNakkis() as $nakki) {
            if (true == $nakki->isDisableBookings()) {
                continue;
            }
            foreach ($selected as $booking) {
                if ($booking->getNakki() == $nakki) {
                    $nakkis = $this->addNakkiToArray(
                        $nakkis,
                        $booking,
                        $locale,
                    );
                    break;
                }
            }
            if (
                !\array_key_exists(
                    $nakki->getDefinition()->getName($locale),
                    $nakkis,
                )
            ) {
                // try to prevent displaying same nakki to 2 different users using the system at the same time
                $bookings = $nakki->getNakkiBookings()->toArray();
                shuffle($bookings);
                foreach ($bookings as $booking) {
                    if (null === $booking->getMember()) {
                        $nakkis = $this->addNakkiToArray(
                            $nakkis,
                            $booking,
                            $locale,
                        );
                        break;
                    }
                }
            }
        }

        return $nakkis;
    }

    protected function addNakkiToArray(array $nakkis, $booking, $locale): array
    {
        $name = $booking->getNakki()->getDefinition()->getName($locale);
        $duration = $booking
            ->getStartAt()
            ->diff($booking->getEndAt())
            ->format('%h');
        $nakkis[$name]['description'] = $booking
            ->getNakki()
            ->getDefinition()
            ->getDescription($locale);
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;

        return $nakkis;
    }

    #[
        Route(
            path: [
                'fi' => '/{year}/{slug}/valmis',
                'en' => '/{year}/{slug}/complete',
            ],
            name: 'entropy_event_shop_complete',
            requirements: [
                'year' => "\d+",
            ],
        ),
    ]
    public function complete(
        Request $request,
        #[
            MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)'),
        ]
        Event $event,
        StripeService $stripe,
        CheckoutRepository $cRepo,
    ): Response {
        $sessionId = $request->get('session_id');
        $stripeSession = $stripe->getCheckoutSession($sessionId);
        if ('open' == $stripeSession->status) {
            $this->addFlash('warning', 'e30v.checkout.open');

            return $this->redirectToRoute('event_stripe_checkouts', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ]);
        }
        $email = '';
        if ('complete' == $stripeSession->status) {
            $checkout = $cRepo->findOneBy(['stripeSessionId' => $sessionId]);
            $cart = $checkout->getCart();
            $email = $cart->getEmail();
            $request->getSession()->remove('cart');
        }

        return $this->render('event/shop_complete.html.twig', [
            'event' => $event,
            'email' => $email,
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
            !$this->publicationDecider->isPublished($event)
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
            !$this->publicationDecider->isPublished($event)
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
            (!$this->publicationDecider->isPublished($event)
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
            !$this->publicationDecider->isPublished($event)
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
            !$this->publicationDecider->isPublished($event)
            && !$user instanceof UserInterface
        ) {
            throw $this->createAccessDeniedException('');
        }

        return $this->render('event/safer_space.html.twig', [
            'event' => $event,
        ]);
    }
}
