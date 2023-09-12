<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/kalenteri.ics',
            'en' => '/calendar.ics',
        ],
        name: 'entropy_event_calendar',
    )]
    public function eventCalendar(
        Request $request,
        EventRepository $eventR,
    ): Response {
        $locale = $request->getLocale();
        $cEvents = [];
        $events = $eventR->findCalendarEvents();
        foreach ($events as $event) {
            $uid = new UniqueIdentifier('event/' . $event->getId());
            $url = new Uri($event->getUrlByLang($locale));
            $start = $event->getEventDate();
            $end = $event->getUntil();
            if (is_null($end)) {
                $end = clone $start;
                $end = $end->modify('+2hours');
            }
            $occurance = new TimeSpan(new DateTime($start, false), new DateTime($end, false));
            $timestamp = new Timestamp($event->getUpdatedAt());
            $e = (new Event($uid))
                ->setSummary($event->getNameByLang($locale))
                ->setDescription($event->getContentByLang($locale))
                ->setOccurrence($occurance)
                ->setUrl($url);
            $e->touch($timestamp);
            $cEvents[] = $e;
        }
        $calendar = new Calendar($cEvents);
        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="entropy.ics"');

        return new Response($calendarComponent);
    }
}
