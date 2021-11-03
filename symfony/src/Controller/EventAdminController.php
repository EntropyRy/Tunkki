<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;

final class EventAdminController extends CRUDController
{
    public function artistListAction()
    {
        $event = $this->admin->getSubject();
        $infos = $event->getEventArtistInfos();
        return $this->renderWithExtraParams('admin/event/artist_list.html.twig', [
            'event' => $event, 
            'infos' => $infos
        ]);
    }
    public function nakkiListAction()
    {
        $event = $this->admin->getSubject();
        $nakkis = $event->getNakkiBookings();
        $emails = []; 
        foreach ($nakkis as $nakki){
            $member = $nakki->getMember();
            if ($member){
                $emails[$member->getId()] = $member->getEmail();
            }
        }
        $emails = implode(';', $emails);
        return $this->renderWithExtraParams('admin/event/nakki_list.html.twig', [
            'event' => $event, 
            'nakkiBookings' => $nakkis, 
            'emails' => $emails
        ]);
    }
    public function rsvpAction()
    {
        $event = $this->admin->getSubject();
        $rsvps = $event->getRSVPs();
        return $this->renderWithExtraParams('admin/event/rsvps.html.twig', [
            'event' => $event, 'rsvps' => $rsvps
        ]);
    }
}

