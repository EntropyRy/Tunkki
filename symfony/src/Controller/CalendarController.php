<?php

namespace App\Controller;

use Eluceo\iCal\Domain\ValueObject\Alarm\DisplayAction;
use Eluceo\iCal\Domain\ValueObject\Alarm\RelativeTrigger;
use App\Entity\Event;
use App\Entity\User;
use App\Form\CalendarConfigType;
use App\Repository\EventRepository;
use Eluceo\iCal\Domain\ValueObject\Alarm;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event as CalendarEvent;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Sqids\Sqids;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Eluceo\iCal\Domain\Entity\TimeZone;
use DateTimeZone as PhpDateTimeZone;

class CalendarController extends AbstractController
{
    #[
        Route(
            path: [
                "fi" => "/profiili/kalenteri",
                "en" => "/profile/calendar",
            ],
            name: "entropy_event_calendar_config",
        ),
    ]
    public function eventCalendarConfig(
        Request $request,
        UrlGeneratorInterface $urlG,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);
        if ($user == null) {
            throw new UnauthorizedHttpException("now allowed");
        }
        $form = $this->createForm(CalendarConfigType::class);
        $url = null;
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $sqid = new Sqids();
            foreach ($form->getData() as $value) {
                $array[] = $value ? 1 : 0;
            }
            $array[] = $user->getId();
            $id = $sqid->encode($array);
            $url = $urlG->generate(
                "entropy_event_calendar",
                ["hash" => $id],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $this->addFlash("success", "calendar.url_generated");
        }
        return $this->render("profile/calendar.html.twig", [
            "form" => $form,
            "url" => $url,
        ]);
    }
    #[
        Route(
            path: [
                "fi" => "/{hash}/kalenteri.ics",
                "en" => "/{hash}/calendar.ics",
            ],
            name: "entropy_event_calendar",
        ),
    ]
    public function eventCalendar(
        Request $request,
        EventRepository $eventR,
    ): Response {
        $sqid = new Sqids();
        $locale = $request->getLocale();
        $config = $sqid->decode($request->get("hash"));
        $cEvents = [];
        $events = $eventR->findCalendarEvents();
        foreach ($events as $event) {
            if ($event->getType() == "event" && $config[0] == 1) {
                $cEvents[] = $this->addEvent($event, $config[1], $locale);
            }
            if (
                ($event->getType() == "clubroom" ||
                    $event->getType() == "stream") &&
                $config[2] == 1
            ) {
                $cEvents[] = $this->addEvent($event, $config[3], $locale);
            }
            if ($event->getType() == "meeting" && $config[4] == 1) {
                $cEvents[] = $this->addEvent($event, $config[5], $locale);
            }
        }
        $calendar = new Calendar($cEvents);
        $calendar->addTimeZone(
            TimeZone::createFromPhpDateTimeZone(
                new PhpDateTimeZone("Europe/Helsinki"),
                new \DateTimeImmutable("now-30years"),
                new \DateTimeImmutable("now+30years"),
            ),
        );

        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        header("Content-Type: text/calendar; charset=utf-8");
        header('Content-Disposition: attachment; filename="entropy.ics"');

        return new Response($calendarComponent);
    }
    protected function addEvent(
        Event $event,
        $notification,
        $locale,
    ): CalendarEvent {
        $uid = new UniqueIdentifier("event/" . $event->getId());
        $url = new Uri($event->getUrlByLang($locale));
        $start = $event->getEventDate();
        $end = $event->getUntil();
        $occurance = new TimeSpan(
            new DateTime($start, false),
            new DateTime($end, true),
        );
        $timestamp = new Timestamp($event->getUpdatedAt());
        $e = new CalendarEvent($uid)
            ->setSummary($event->getNameByLang($locale))
            ->setDescription($event->getContentByLang($locale))
            ->setOccurrence($occurance)
            ->setUrl($url);
        if ($notification == 1) {
            $text =
                $locale == "fi"
                    ? "Muistutus huomisesta Entropy tapahtumasta!"
                    : "Reminder for Entropy event tommorrow!";
            $e->addAlarm(
                new Alarm(
                    new DisplayAction($text),
                    new RelativeTrigger(
                        \DateInterval::createFromDateString("-1 day"),
                    )->withRelationToStart(),
                ),
            );
        }
        $physicalLocation = $event->getLocation();
        $onlineUrl = $event->getWebMeetingUrl();
        if ($physicalLocation instanceof \App\Entity\Location && $onlineUrl) {
            $composite = sprintf(
                "%s – %s (Online: %s)",
                $physicalLocation->getNameByLocale($locale),
                $physicalLocation->getStreetAddress(),
                $onlineUrl,
            );
            $e->setLocation(
                new Location($composite, $physicalLocation->getNameByLocale($locale)),
            );
        } elseif ($physicalLocation instanceof \App\Entity\Location) {
            $e->setLocation(
                new Location(
                    $physicalLocation->getStreetAddress(),
                    $physicalLocation->getNameByLocale($locale),
                ),
            );
        } elseif ($onlineUrl) {
            $e->setLocation(new Location($onlineUrl));
        }
        $e->touch($timestamp);
        return $e;
    }
}
