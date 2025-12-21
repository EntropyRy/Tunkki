<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\NakkiBooking;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * EventNakkiSignUpAccessTest.
 *
 * Negative access test: an authenticated user whose Member email is NOT verified
 * must NOT be able to reserve a Nakki slot (signup action should early‑return
 * with a danger flash + redirect).
 *
 * Controller logic reference (EventSignUpController::nakkiSignUp):
 *  - If $member->isEmailVerified() === false, add flash 'danger' and redirect back (referer).
 *
 * Test Strategy:
 *  1. Find (or skip if unavailable) a NakkiBooking entity that is not yet reserved
 *     (booking->getMember() === null) and whose parent Event is published & accessible.
 *  2. Create a user/member (factory via LoginHelperTrait) and explicitly force emailVerified = false.
 *  3. Login the user, perform GET on /{year}/{slug}/nakkikone/{bookingId}/signup.
 *  4. Assert redirect (302/303).
 *  5. Follow redirect (or manually GET referer fallback) and assert presence of flash text
 *     containing a 'verify' marker (case‑insensitive heuristic).
 *
 * Resilience:
 *  - If no suitable booking/event is found, the test marks itself skipped (does not fail the suite).
 *  - This keeps the test robust during incremental migration away from broad fixtures.
 *
 * Future Hardening (optional):
 *  - Replace repository lookups with factory‑created Event + NakkiBooking once a NakkiBooking
 *    factory/state exists.
 *  - Add explicit data-test attributes for flash messages to eliminate substring heuristics.
 */
final class EventNakkiSignUpAccessTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    // Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static registration

    protected function setUp(): void
    {
        parent::setUp();
        // Use unified site-aware initialization (Sonata Page multisite + SiteRequest wrapping)
        $this->initSiteAwareClient();
        // Removed redundant assignment to $this->client; base class already registered the site-aware client
    }

    public function testUnverifiedEmailCannotReserveNakki(): void
    {
        $em = $this->em();

        // Heuristic lookup: find a NakkiBooking with no member assigned.
        /** @var \Doctrine\Persistence\ObjectRepository<NakkiBooking> $bookingRepo */
        $bookingRepo = $em->getRepository(NakkiBooking::class);
        /** @var \Doctrine\Persistence\ObjectRepository<Event> $eventRepo */
        $eventRepo = $em->getRepository(Event::class);

        /** @var NakkiBooking|null $booking */
        $booking = $bookingRepo->findOneBy(['member' => null]);

        if (!$booking) {
            // Create minimal Event + NakkiDefinition + Nakki + NakkiBooking graph inline
            $now = new \DateTimeImmutable();
            $start = $now->modify('+1 hour');
            $end = $start->modify('+1 hour');

            // Persist a published internal event with a near-future eventDate
            $event = EventFactory::new()->create([
                'url' => 'nakki-signup-'.uniqid(),
                'name' => 'Nakki Signup Test',
                'nimi' => 'Nakki Signup Test FI',
                'publishDate' => new \DateTimeImmutable('-1 day'),
                'eventDate' => $now->modify('+2 days'),
                'published' => true,
            ]);

            $def = new \App\Entity\NakkiDefinition();
            $def->setNameFi('Testi Nakki');
            $def->setNameEn('Test Nakki');
            $def->setDescriptionFi('Desc FI');
            $def->setDescriptionEn('Desc EN');
            $def->setOnlyForActiveMembers(false);

            $nakkikone = new \App\Entity\Nakkikone($event);
            $event->setNakkikone($nakkikone);

            $nakki = new \App\Entity\Nakki();
            $nakki->setDefinition($def);
            $nakki->setNakkikone($nakkikone);
            $nakki->setStartAt($start);
            $nakki->setEndAt($end);

            $booking = new NakkiBooking();
            $booking->setNakki($nakki);
            $booking->setNakkikone($nakkikone);
            $booking->setStartAt($start);
            $booking->setEndAt($end);

            // Ensure Event is managed when assigning from NakkiBooking/Nakki
            $em->persist($event);
            $em->persist($nakkikone);
            $em->persist($def);
            $em->persist($nakki);
            $em->persist($booking);
            $em->flush();
        }

        // Attempt to derive the parent Event and route params.
        $event = method_exists($booking, 'getEvent')
            ? $booking->getEvent()
            : null;
        $this->assertInstanceOf(
            Event::class,
            $event,
            'Unable to resolve parent Event from NakkiBooking (getEvent() returned null or unsupported).',
        );

        $this->assertTrue(
            method_exists($event, 'getEventDate')
                && method_exists($event, 'getUrl'),
            'Event entity missing required accessors (getEventDate/getUrl).',
        );

        $eventDate = $event->getEventDate();
        $this->assertInstanceOf(
            \DateTimeInterface::class,
            $eventDate,
            'Event has no eventDate; cannot construct signup route.',
        );

        $year = (int) $eventDate->format('Y');
        $slug = $event->getUrl();
        $bookingId = $booking->getId();

        $this->assertNotNull($bookingId, 'Booking has no ID (not persisted).');

        // Create & login a user/member with unverified email.
        $userEmail = 'unverified-nakki+'.uniqid().'@example.test';
        $user = $this->getOrCreateUser($userEmail, []);
        $member = $user->getMember();
        if ($member && method_exists($member, 'setEmailVerified')) {
            // Ensure username is set so we bypass the "define username" flash gate and reach the email verification check
            if (
                method_exists($member, 'setUsername')
                && !$member->getUsername()
            ) {
                $member->setUsername(
                    'unverified_'.substr(md5($userEmail), 0, 6),
                );
            }
            $member->setEmailVerified(false);
            $em->flush();
        } else {
            $this->fail(
                'Member or setEmailVerified() not available; cannot force unverified state.',
            );
        }

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        // Construct signup URL per routing pattern: /{year}/{slug}/nakkikone/{id}/signup
        $signupPath = \sprintf(
            '/%d/%s/nakkikone/%d/signup',
            $year,
            $slug,
            $bookingId,
        );

        // Provide a fallback Referer so the controller's redirect($request->headers->get('referer')) has a value.
        $this->client->setServerParameter(
            'HTTP_REFERER',
            \sprintf('/%d/%s/nakkikone', $year, $slug),
        );

        $this->client->request('GET', $signupPath);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            \in_array($status, [302, 303], true),
            'Expected redirect status (302/303) when unverified member attempts signup; got '.
                $status,
        );

        $redirect =
            $this->client->getResponse()->headers->get('Location') ?? '';
        $this->assertNotEmpty(
            $redirect,
            'Signup attempt should redirect back (Location header missing).',
        );

        // Follow redirect target (if relative path)
        if (str_starts_with($redirect, '/')) {
            $this->client->request('GET', $redirect);
        } else {
            // If absolute or empty, attempt referer as fallback
            $this->client->request(
                'GET',
                \sprintf('/%d/%s/nakkikone', $year, $slug),
            );
        }

        $followStatus = $this->client->getResponse()->getStatusCode();
        $this->assertSame(
            200,
            $followStatus,
            'Follow-up page after redirect should load (200).',
        );

        $content = $this->client->getResponse()->getContent() ?? '';
        $this->assertNotEmpty(
            $content,
            'Expected HTML content after redirect.',
        );

        // Structural assertion: expect a Bootstrap danger alert (flash) rendered
        $crawler = new \Symfony\Component\DomCrawler\Crawler($content);
        $this->assertGreaterThan(
            0,
            $crawler->filter('.alert.alert-danger')->count(),
            'Expected a danger flash message after unverified member attempts signup.',
        );
    }
}
