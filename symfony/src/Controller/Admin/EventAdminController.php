<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends CRUDController<Event>
 */
final class EventAdminController extends CRUDController
{
    public function nakkiListAction(): Response
    {
        $event = $this->admin->getSubject();
        $nakkikone = $event->getNakkikone();
        $nakkis = $nakkikone?->getBookings() ?? [];
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
            'emails' => $emails,
        ]);
    }

    public function rsvpAction(): Response
    {
        $event = $this->admin->getSubject();
        $rsvps = $event->getRSVPs();

        // $email_url = $this->admin->generateUrl('rsvpEmail', ['id' => $event->getId()]);
        return $this->render('admin/event/rsvps.html.twig', [
            'event' => $event,
            'rsvps' => $rsvps,
            // 'email_url' => $email_url
        ]);
    }
}
