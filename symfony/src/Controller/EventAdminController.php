<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends CRUDController<object>
 */
final class EventAdminController extends CRUDController
{
    public function artistListAction(): Response
    {
        $event = $this->admin->getSubject();
        $infos = $event->getEventArtistInfos();
        return $this->render('admin/event/artist_list.html.twig', [
            'event' => $event,
            'infos' => $infos
        ]);
    }
    public function nakkiListAction(): Response
    {
        $event = $this->admin->getSubject();
        $nakkis = $event->getNakkiBookings();
        $emails = [];
        foreach ($nakkis as $nakki) {
            $member = $nakki->getMember();
            if ($member) {
                $emails[$member->getId()] = $member->getEmail();
            }
        }
        $emails = implode(';', $emails);
        return $this->render('admin/event/nakki_list.html.twig', [
            'event' => $event,
            'nakkiBookings' => $nakkis,
            'emails' => $emails
        ]);
    }
    public function rsvpAction(): Response
    {
        $event = $this->admin->getSubject();
        $rsvps = $event->getRSVPs();
        //$email_url = $this->admin->generateUrl('rsvpEmail', ['id' => $event->getId()]);
        return $this->render('admin/event/rsvps.html.twig', [
            'event' => $event,
            'rsvps' => $rsvps,
            //'email_url' => $email_url
        ]);
    }
}
