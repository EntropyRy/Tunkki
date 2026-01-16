<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Location as PhysicalLocation;
use App\Entity\User;
use App\Form\CalendarConfigType;
use App\Repository\EventRepository;
use DateTimeZone as PhpDateTimeZone;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event as CalendarEvent;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Domain\ValueObject\Alarm;
use Eluceo\iCal\Domain\ValueObject\Alarm\DisplayAction;
use Eluceo\iCal\Domain\ValueObject\Alarm\RelativeTrigger;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Sqids\Sqids;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Calendar configuration and ICS feed generation.
 *
 * The calendar hash encodes user preferences as a Sqids-encoded array:
 * [0] add_events (1=yes, 0=no)
 * [1] notify_events (1=yes, 0=no)
 * [2] add_clubroom_and_stream (1=yes, 0=no)
 * [3] notify_clubroom_and_stream (1=yes, 0=no)
 * [4] add_meetings (1=yes, 0=no)
 * [5] notify_meetings (1=yes, 0=no)
 * [6] user_id.
 */
class CalendarController extends AbstractController
{
    private const string TIMEZONE = 'Europe/Helsinki';

    // Config array indices for decoded Sqids hash
    private const int CFG_ADD_EVENTS = 0;
    private const int CFG_NOTIFY_EVENTS = 1;
    private const int CFG_ADD_CLUBROOM = 2;
    private const int CFG_NOTIFY_CLUBROOM = 3;
    private const int CFG_ADD_MEETINGS = 4;
    private const int CFG_NOTIFY_MEETINGS = 5;

    #[Route(
        path: [
            'fi' => '/profiili/kalenteri',
            'en' => '/profile/calendar',
        ],
        name: 'entropy_event_calendar_config',
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function eventCalendarConfig(
        Request $request,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$user->getMember()->isEmailVerified()) {
            $this->addFlash('warning', 'calendar.email_verification_required');

            return $this->redirectToRoute('profile_resend_verification', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $form = $this->createForm(CalendarConfigType::class);
        $url = null;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $url = $this->generateCalendarUrl($form->getData(), $user, $urlGenerator);
            $this->addFlash('success', 'calendar.url_generated');
        }

        return $this->render('profile/calendar.html.twig', [
            'form' => $form,
            'url' => $url,
        ]);
    }

    #[Route(
        path: [
            'fi' => '/{hash}/kalenteri.ics',
            'en' => '/{hash}/calendar.ics',
        ],
        name: 'entropy_event_calendar',
    )]
    public function eventCalendar(
        Request $request,
        EventRepository $eventRepository,
    ): Response {
        $sqid = new Sqids();
        $locale = $request->getLocale();
        $config = $sqid->decode($request->attributes->getString('hash'));

        $calendarEvents = $this->buildCalendarEvents($eventRepository->findCalendarEvents(), $config, $locale);

        $calendar = new Calendar($calendarEvents);
        $calendar->addTimeZone(
            TimeZone::createFromPhpDateTimeZone(
                new PhpDateTimeZone(self::TIMEZONE),
                new \DateTimeImmutable('now-30years'),
                new \DateTimeImmutable('now+30years'),
            ),
        );

        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        $response = new Response($calendarComponent->__toString());
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="entropy.ics"');

        return $response;
    }

    /**
     * Generate the calendar subscription URL from form data.
     *
     * @param array<string, bool>|null $formData
     */
    private function generateCalendarUrl(
        ?array $formData,
        User $user,
        UrlGeneratorInterface $urlGenerator,
    ): string {
        $sqid = new Sqids();
        $configArray = [];

        if (\is_array($formData)) {
            foreach ($formData as $value) {
                $configArray[] = $value ? 1 : 0;
            }
        }

        $configArray[] = $user->getId();
        $hash = $sqid->encode($configArray);

        return $urlGenerator->generate(
            'entropy_event_calendar',
            ['hash' => $hash],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /**
     * Build calendar events from domain events based on user configuration.
     *
     * @param iterable<Event> $events
     * @param array<int>      $config
     *
     * @return array<CalendarEvent>
     */
    private function buildCalendarEvents(iterable $events, array $config, string $locale): array
    {
        $calendarEvents = [];

        foreach ($events as $event) {
            $type = $event->getType();

            if ('event' === $type && ($config[self::CFG_ADD_EVENTS] ?? 0) === 1) {
                $calendarEvents[] = $this->createCalendarEvent(
                    $event,
                    ($config[self::CFG_NOTIFY_EVENTS] ?? 0) === 1,
                    $locale,
                );
            }

            if (('clubroom' === $type || 'stream' === $type) && ($config[self::CFG_ADD_CLUBROOM] ?? 0) === 1) {
                $calendarEvents[] = $this->createCalendarEvent(
                    $event,
                    ($config[self::CFG_NOTIFY_CLUBROOM] ?? 0) === 1,
                    $locale,
                );
            }

            if ('meeting' === $type && ($config[self::CFG_ADD_MEETINGS] ?? 0) === 1) {
                $calendarEvents[] = $this->createCalendarEvent(
                    $event,
                    ($config[self::CFG_NOTIFY_MEETINGS] ?? 0) === 1,
                    $locale,
                );
            }
        }

        return $calendarEvents;
    }

    /**
     * Create a single iCal event from a domain Event.
     */
    private function createCalendarEvent(
        Event $event,
        bool $addNotification,
        string $locale,
    ): CalendarEvent {
        $uid = new UniqueIdentifier('event/'.$event->getId());
        $url = new Uri($event->getUrlByLang($locale));
        $timeSpan = $this->createTimeSpan($event);
        $timestamp = new Timestamp($event->getUpdatedAt());

        $calendarEvent = new CalendarEvent($uid)
            ->setSummary($event->getNameByLang($locale))
            ->setDescription($this->sanitizeEventDescription($event->getContentForTwig($locale)))
            ->setOccurrence($timeSpan)
            ->setUrl($url);

        if ($addNotification) {
            $calendarEvent->addAlarm($this->createReminderAlarm($locale));
        }

        $this->setEventLocation($calendarEvent, $event, $locale);
        $calendarEvent->touch($timestamp);

        return $calendarEvent;
    }

    /**
     * Create a TimeSpan for the event with proper timezone handling.
     */
    private function createTimeSpan(Event $event): TimeSpan
    {
        $tz = new PhpDateTimeZone(self::TIMEZONE);

        $start = \DateTimeImmutable::createFromInterface($event->getEventDate())->setTimezone($tz);

        $end = $event->getUntil();
        if ($end instanceof \DateTimeInterface) {
            $end = \DateTimeImmutable::createFromInterface($end)->setTimezone($tz);
        }

        // Both start and end are timezone-aware (Europe/Helsinki) and non-floating.
        // This prevents macOS Calendar from interpreting times differently.
        return new TimeSpan(
            new DateTime($start, false),
            new DateTime($end, false),
        );
    }

    /**
     * Sanitize event description by stripping HTML and decoding entities.
     */
    private function sanitizeEventDescription(?string $content): string
    {
        return html_entity_decode(strip_tags((string) $content));
    }

    /**
     * Create a reminder alarm for one day before the event.
     */
    private function createReminderAlarm(string $locale): Alarm
    {
        $text = 'fi' === $locale
            ? 'Muistutus huomisesta Entropy tapahtumasta!'
            : 'Reminder for Entropy event tommorrow!';

        return new Alarm(
            new DisplayAction($text),
            new RelativeTrigger(
                \DateInterval::createFromDateString('-1 day'),
            )->withRelationToStart(),
        );
    }

    /**
     * Set the location on a calendar event based on physical location and/or online URL.
     */
    private function setEventLocation(CalendarEvent $calendarEvent, Event $event, string $locale): void
    {
        $physicalLocation = $event->getLocation();
        $onlineUrl = $event->getWebMeetingUrl();

        if ($physicalLocation instanceof PhysicalLocation && $onlineUrl) {
            $composite = \sprintf(
                '%s – %s (Online: %s)',
                $physicalLocation->getNameByLocale($locale),
                $physicalLocation->getStreetAddress(),
                $onlineUrl,
            );
            $calendarEvent->setLocation(
                new Location($composite, $physicalLocation->getNameByLocale($locale)),
            );
        } elseif ($physicalLocation instanceof PhysicalLocation) {
            $calendarEvent->setLocation(
                new Location(
                    $physicalLocation->getStreetAddress(),
                    $physicalLocation->getNameByLocale($locale),
                ),
            );
        } elseif ($onlineUrl) {
            $calendarEvent->setLocation(new Location($onlineUrl));
        }
    }
}
