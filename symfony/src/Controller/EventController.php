<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Event;
use App\Entity\Product;
use App\Entity\Ticket;
use App\Entity\Member;
use App\Entity\RSVP;
use App\Entity\User;
use App\Form\RSVPType;
use App\Form\CartType;
use App\Helper\AppStripeClient;
use App\Helper\ReferenceNumber;
use App\Repository\CartRepository;
use App\Repository\TicketRepository;
use App\Repository\CheckoutRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SimpleSoftwareIO\QrCode\Generator;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends Controller
{
    public function oneId(
        Event $event,
    ): Response {
        if ($event->getUrl()) {
            if ($event->getExternalUrl()) {
                return new RedirectResponse($event->getUrl());
            }
            return $this->redirectToRoute('entropy_event_slug', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        $template = $event->getTemplate() ? $event->getTemplate() : 'event.html.twig';
        return $this->render($template, [
            'event' => $event,
        ]);
    }
    public function oneSlug(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        TranslatorInterface $trans,
        TicketRepository $ticketRepo,
        EntityManagerInterface $em
    ): Response {
        $ticket = null;
        $form = null;
        $ticketCount = null;
        $user = $this->getUser();
        if ($event->getTicketsEnabled() && $user) {
            assert($user instanceof User);
            $member = $user->getMember();
            $ticket = $ticketRepo->findOneBy(['event' => $event, 'owner' => $member]); //own ticket
            $ticketCount = $ticketRepo->findAvailableTicketsCount($event);
        }
        if ($event->getRsvpSystemEnabled() && is_null($user)) {
            $rsvp = new RSVP();
            $form = $this->createForm(RSVPType::class, $rsvp);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $rsvp = $form->getData();
                $repo = $em->getRepository(Member::class);
                assert($repo instanceof MemberRepository);
                $exists = $repo->findByEmailOrName($rsvp->getEmail(), $rsvp->getFirstName(), $rsvp->getLastName());
                if ($exists) {
                    $this->addFlash('warning', $trans->trans('rsvp.email_in_use'));
                } else {
                    $rsvp->setEvent($event);
                    try {
                        $em->persist($rsvp);
                        $em->flush();
                        $this->addFlash('success', $trans->trans('rsvp.rsvpd_succesfully'));
                    } catch (\Exception) {
                        $this->addFlash('warning', $trans->trans('rsvp.already_rsvpd'));
                    }
                }
            }
        }
        if (!$event->isPublished() && is_null($user)) {
            throw $this->createAccessDeniedException('');
        }
        $template = $event->getTemplate() ? $event->getTemplate() : 'event.html.twig';
        return $this->render($template, [
            'event' => $event,
            'rsvpForm' => $form,
            'ticket' => $ticket,
            'ticketsAvailable' => $ticketCount,
        ]);
    }
    #[Route(
        path: [
            'fi' => '/{year}/{slug}/kauppa',
            'en' => '/{year}/{slug}/shop',
        ],
        name: 'entropy_event_shop',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function eventShop(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        CartRepository $cartR
    ): Response {
        $user = $this->getUser();
        if (!$event->isPublished() && is_null($user)) {
            throw $this->createAccessDeniedException('');
        }
        $email = null;
        if ($user != null) {
            assert($user instanceof User);
            $email = $user->getEmail();
        }
        $products = $event->getProducts();
        $session = $request->getSession();
        $cart = new Cart();
        $cartId = $session->get('cart');
        if (!is_null($cartId)) {
            $cart = $cartR->findOneBy(['id' => $cartId]);
        }
        $cart->setProducts($products);
        if ($cart->getEmail() == null) {
            $cart->setEmail($email);
        }
        $form = $this->createForm(CartType::class, $cart);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $cart = $form->getData();
            $cartR->save($cart, true);

            $session->set('cart', $cart->getId());
            return $this->redirectToRoute('event_stripe_checkouts', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        return $this->render('event/shop.html.twig', [
            'event' => $event,
            'form' => $form
        ]);
    }
    #[Route(
        path: [
            'fi' => '/{year}/{slug}/valmis',
            'en' => '/{year}/{slug}/complete',
        ],
        name: 'entropy_event_shop_complete',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function complete(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        AppStripeClient $stripe,
        CheckoutRepository $cRepo,
    ): Response {
        $sessionId = $request->get('session_id');
        $stripeSession = $stripe->getCheckoutSession($sessionId);
        if ($stripeSession->status == 'open') {
            $this->addFlash('warning', 'e30v.checkout.open');
            return $this->redirectToRoute('event_stripe_checkouts', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        $qrs = [];
        if ($stripeSession->status == 'complete') {
            $checkout = $cRepo->findOneBy(['stripeSessionId' => $sessionId]);
            $cart = $checkout->getCart();
            $email = $cart->getEmail();
            $products = $cart->getProducts();
            $tickets = [];
            if ($checkout->getStatus() == 2) {
                foreach ($products as $cartItem) {
                    $product = $cartItem->getProduct();
                    $quantity = $cartItem->getQuantity();
                    if ($product->isTicket()) {
                        $tickets = $this->giveEventTicketToEmail($event, $product, $quantity, $email);
                    }
                }
                $qrGenerator = new Generator();
                foreach ($tickets as $ticket) {
                    $qrs[] = base64_encode($qrGenerator
                        ->format('png')
                        ->eye('circle')
                        ->style('round')
                        ->size(600)
                        ->gradient(0, 40, 40, 40, 40, 0, 'radial')
                        ->errorCorrection('H')
                        ->merge('images/golden-logo.png', .2)
                        ->generate((string)$ticket->getReferenceNumber()));
                }
                try {
                    $this->sendTicketQrEmail(
                        $event->getNameByLang($request->getLocale()),
                        $email,
                        $qrs,
                        $event->getPicture()
                    );
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
            $checkout->setStatus(2);
            $cRepo->save($checkout, true);
        }
        return $this->render('event/shop_complete.html.twig', [
            'event' => $event,
            'qrs' => $qrs
        ]);
    }
    public function __construct(
        private readonly MemberRepository $memberRepo,
        private readonly TicketRepository $ticketRepo,
        private readonly ReferenceNumber $rn,
        private readonly MailerInterface $mailer
    ) {
    }
    private function sendTicketQrEmail($eventName, $to, $qrs, $img)
    {
        foreach ($qrs as $x => $qr) {
            $mail =  (new TemplatedEmail())
                ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
                ->to($to)
                ->replyTo('info@entropy.fi')
                ->subject('[' . $eventName . '] Your ticket #' . ($x + 1) . ' / Lippusi #' . ($x + 1))
                ->addPart((new DataPart(base64_decode($qr), 'ticket', 'image/png', 'base64'))->asInline())
                ->htmlTemplate('emails/ticket.html.twig')
                ->context(['body' => $qr, 'links' => true, 'img' => $img]);
            $this->mailer->send($mail);
        }
    }
    private function giveEventTicketToEmail(
        Event $event,
        Product $product,
        int $quantity,
        $email
    ): array {
        $tickets = [];
        // check if is members email
        $member = $this->memberRepo->getByEmail($email);
        for ($i = 1; $i <= $quantity; $i++) {
            $ticket = new Ticket();
            $ticket->setEvent($event);
            $ticket->setStripeProductId($product->getStripeId());
            $ticket->setPrice($product->getAmount());
            $ticket->setStatus('paid');
            if (is_null($member)) {
                $ticket->setEmail($email);
            } else {
                $ticket->setOwner($member);
            }
            $this->ticketRepo->save($ticket, true);
            $ticket->setReferenceNumber($this->rn->calculateReferenceNumber($ticket, 9000, 909));
            $this->ticketRepo->save($ticket, true);
            $tickets[] = $ticket;
        }
        return $tickets;
    }
    #[Route(
        path: [
            'fi' => '/{year}/{slug}/artistit',
            'en' => '/{year}/{slug}/artists',
        ],
        name: 'entropy_event_artists',
        requirements: [
            'year' => '\d+',
        ]
    )]
    public function eventArtists(
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
    ): Response {
        $user = $this->getUser();
        if (!$event->isPublished() && is_null($user)) {
            throw $this->createAccessDeniedException('');
        }
        return $this->render('event/artists.html.twig', [
            'event' => $event,
        ]);
    }
}
