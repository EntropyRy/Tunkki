<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Security;
use App\Repository\TicketRepository;
use App\Entity\Event;
use App\Entity\RSVP;
use App\Form\RSVPType;

class EventController extends Controller
{
    protected $em;
    public function oneId(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans, SeoPageInterface $seo)
    {
        $eventid = $request->get('id');
        $lang = $request->getLocale();
        if (empty($eventid)) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $this->em = $this->getDoctrine()->getManager();
        $event = $this->em->getRepository(Event::class)->findOneBy(['id' => $eventid]);
        if (!$event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        if (empty($event->getUrl()) && $event->getexternalUrl()) {
            return new RedirectResponse("/");
        }
        if ($event->getUrl()) {
            if ($event->getexternalUrl()) {
                return new RedirectResponse($event->getUrl());
            }
            return $this->redirectToRoute('entropy_event_slug', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        $page = $cms->retrieve()->getCurrentPage();
        $this->setMetaData($lang, $event, $page, $seo);
        return $this->render('event.html.twig', [
                'event' => $event,
                'page' => $page
            ]);
    }
    public function oneSlug(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans, SeoPageInterface $seo, TicketRepository $ticketRepo): Response {
        $slug = $request->get('slug');
        $year = $request->get('year');
        if (empty($slug)) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $this->em = $this->getDoctrine()->getManager();
        $event = $this->em->getRepository(Event::class)
            ->findEventBySlugAndYear($slug, $year);
        if (!$event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $lang = $request->getLocale();
        $ticket = null;
        $formview = null;
        $ticketCount = null;
        $page = $cms->retrieve()->getCurrentPage();

        if ($event->getTicketsEnabled() && $this->getUser()) {
            $member = $this->getUser()->getMember();
            $ticket = $ticketRepo->findOneBy(['event' => $event, 'owner' => $member]); //own ticket
            $ticketCount = $ticketRepo->findAvailableTicketsCount($event);
        }
        $this->setMetaData($lang, $event, $page, $seo);
        if ($event->getRsvpSystemEnabled() && !$this->getUser()) {
            $rsvp = new RSVP();
            $form = $this->createForm(RSVPType::class, $rsvp);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $rsvp = $form->getData();
                $repo = $this->em->getRepository('App:Member');
                $exists = $repo->findByEmailOrName($rsvp->getEmail(), $rsvp->getFirstName(), $rsvp->getLastName());
                if ($exists) {
                    $this->addFlash('warning', $trans->trans('rsvp.email_in_use'));
                } else {
                    $rsvp->setEvent($event);
                    try {
                        $this->em->persist($rsvp);
                        $this->em->flush();
                        $this->addFlash('success', $trans->trans('rsvp.rsvpd_succesfully'));
                    } catch (\Exception $e) {
                        $this->addFlash('warning', $trans->trans('rsvp.already_rsvpd'));
                    }
                }
            }
            $formview = $form->createView();
        }
        if (!$event->getPublished() && is_null($this->getUser())) {
            throw $this->createAccessDeniedException('');
        }
        return $this->render('event.html.twig', [
                'event' => $event,
                'page' => $page,
                'rsvpForm' => $formview,
                'ticket' => $ticket,
                'ticketsAvailable' => $ticketCount,
            ]);
    }
    private function setMetaData($lang, $event, $page, $seo): void
    {
        $now = new \DateTime();
        if ($event->getPublished() && $event->getPublishDate() < $now) {
            $title = $event->getNameByLang($lang).' - '. $event->getEventDate()->format('d.m.Y, H:i');
            $page->setTitle($title);
            $seo->addMeta('property', 'og:title', $title)
                ->addMeta('property', 'og:description', $event->getAbstract($lang))
            ;
            if ($event->getType() != 'announcement') {
                $seo->addMeta('property', 'og:type', 'event')
                    ->addMeta('property', 'event:start_time', $event->getEventDate()->format('Y-m-d H:i'));
            }
        }
    }
}
