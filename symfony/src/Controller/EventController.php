<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use App\Repository\TicketRepository;
use App\Repository\EventRepository;
use Sonata\MediaBundle\Provider\ImageProvider;
use App\Entity\Member;
use App\Entity\RSVP;
use App\Form\RSVPType;

class EventController extends Controller
{
    public function oneId(
        Request $request,
        CmsManagerSelector $cms,
        TranslatorInterface $trans,
        SeoPageInterface $seo,
        EventRepository $eRepo
    ): Response {
        $eventid = $request->get('id');
        $lang = $request->getLocale();
        if (empty($eventid)) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $event = $eRepo->findOneBy(['id' => $eventid]);
        if (!$event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        if (empty($event->getUrl()) && $event->getExternalUrl()) {
            return new RedirectResponse("/");
        }
        if ($event->getUrl()) {
            if ($event->getExternalUrl()) {
                return new RedirectResponse($event->getUrl());
            }
            return $this->redirectToRoute('entropy_event_slug', [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()
            ]);
        }
        $page = $cms->retrieve()->getCurrentPage();
        $this->setMetaData($lang, $event, $page, $seo, null);
        return $this->render('event.html.twig', [
                'event' => $event,
                'page' => $page
            ]);
    }
    public function oneSlug(
        Request $request,
        CmsManagerSelector $cms,
        TranslatorInterface $trans,
        SeoPageInterface $seo,
        EventRepository $eRepo,
        TicketRepository $ticketRepo,
        ImageProvider $mediaPro,
        EntityManagerInterface $em
    ): Response {
        $mediaUrl = null;
        $slug = $request->get('slug');
        $year = $request->get('year');
        if (empty($slug)) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $event = $eRepo->findEventBySlugAndYear($slug, $year);
        if (!$event) {
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $lang = $request->getLocale();
        $ticket = null;
        $form = null;
        $ticketCount = null;
        $page = $cms->retrieve()->getCurrentPage();
        if ($event->getPicture() && $event->getPicture()->getProviderName() == $mediaPro->getName()) {
            $format = $mediaPro->getFormatName($event->getPicture(), 'normal');
            $mediaUrl = $mediaPro->generatePublicUrl($event->getPicture(), $format);
        }
        $this->setMetaData($lang, $event, $page, $seo, $mediaUrl);

        if ($event->getTicketsEnabled() && $this->getUser()) {
            $member = $this->getUser()->getMember();
            $ticket = $ticketRepo->findOneBy(['event' => $event, 'owner' => $member]); //own ticket
            $ticketCount = $ticketRepo->findAvailableTicketsCount($event);
        }
        if ($event->getRsvpSystemEnabled() && !$this->getUser()) {
            $rsvp = new RSVP();
            $form = $this->createForm(RSVPType::class, $rsvp);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $rsvp = $form->getData();
                $repo = $em->getRepository(Member::class);
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
        if (!$event->getPublished() && is_null($this->getUser())) {
            throw $this->createAccessDeniedException('');
        }
        return $this->render('event.html.twig', [
                'event' => $event,
                'page' => $page,
                'rsvpForm' => $form,
                'ticket' => $ticket,
                'ticketsAvailable' => $ticketCount,
            ]);
    }
    private function setMetaData($lang, $event, $page, $seo, $mediaUrl): void
    {
        $now = new \DateTime();
        // ei näytetä dataa linkki previewissä ellei tapahtuma ole julkaistu
        if ($event->getPublished() && $event->getPublishDate() < $now) {
            $title = $event->getNameByLang($lang).' - '. $event->getEventDate()->format('d.m.Y, H:i');
            $page->setTitle($title);
            if (!is_null($mediaUrl)) {
                $seo->addMeta('property', 'twitter:image', 'https://entropy.fi'.$mediaUrl);
                $seo->addMeta('property', 'og:image', 'https://entropy.fi'.$mediaUrl);
                $seo->addMeta('property', 'og:image:height', '');
                $seo->addMeta('property', 'og:image:widht', '');
            }
            $seo->addMeta('property', 'twitter:card', "summary_large_image");
            //$seo->addMeta('property', 'twitter:site', "@entropy.fi");
            $seo->addMeta('property', 'twitter:title', $title);
            $seo->addMeta('property', 'twitter:desctiption', $event->getAbstract($lang));
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
