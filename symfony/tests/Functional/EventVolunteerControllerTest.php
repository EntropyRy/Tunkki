<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Nakki;
use App\Entity\NakkiBooking;
use App\Entity\NakkiDefinition;
use App\Entity\Nakkikone;
use App\Entity\RSVP;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiBookingFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkikoneFactory;
use App\Factory\RSVPFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * EventVolunteerControllerTest.
 *
 * Tests for EventVolunteerController covering:
 * - nakkiCancel: Cancel a nakki booking (lines 51-71)
 * - nakkiSignUp: Sign up for a nakki with various validation (lines 82-152)
 * - nakkiAdmin: Admin view for nakkikone (lines 166-173)
 * - nakkikone: Main nakkikone view (lines 183-209)
 * - responsible: View for responsible members (lines 222-247)
 * - rsvp: Event RSVP with duplicate handling (lines 253-299)
 * - getNakkis/buildNakkiArray: Helper methods (lines 307-391)
 */
final class EventVolunteerControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
        $this->em()->clear();
    }

    /**
     * Create an event with enabled nakkikone and booking.
     *
     * @return array{event: Event, nakkikone: Nakkikone, booking: NakkiBooking, definition: NakkiDefinition, nakki: Nakki}
     */
    private function createEventWithNakkikone(bool $requireDifferentTimes = true): array
    {
        $now = new \DateTimeImmutable();
        $eventDate = $now->modify('+7 days');

        $event = EventFactory::new()->published()->create([
            'eventDate' => $eventDate,
            'until' => $eventDate->modify('+8 hours'),
            'url' => 'nakki-test-'.uniqid('', true),
        ]);
        $event = $event instanceof Proxy ? $event->_real() : $event;

        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
            'requireDifferentTimes' => $requireDifferentTimes,
        ]);
        $nakkikone = $nakkikone instanceof Proxy ? $nakkikone->_real() : $nakkikone;

        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Test Nakki',
            'nameEn' => 'Test Nakki EN',
            'descriptionFi' => 'Test description FI',
            'descriptionEn' => 'Test description EN',
            'onlyForActiveMembers' => false,
        ]);
        $definition = $definition instanceof Proxy ? $definition->_real() : $definition;

        $start = $eventDate->setTime(10, 0);
        $end = $start->modify('+2 hours');

        $nakki = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'startAt' => $start,
            'endAt' => $end,
        ]);
        $nakki = $nakki instanceof Proxy ? $nakki->_real() : $nakki;

        $booking = NakkiBookingFactory::new()->free()->create([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $start,
            'endAt' => $end,
        ]);
        $booking = $booking instanceof Proxy ? $booking->_real() : $booking;

        return [
            'event' => $event,
            'nakkikone' => $nakkikone,
            'booking' => $booking,
            'definition' => $definition,
            'nakki' => $nakki,
        ];
    }

    /**
     * Create a verified user with username.
     */
    private function createVerifiedUser(): \App\Entity\User
    {
        $member = MemberFactory::new()->create([
            'username' => 'testuser_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        return $member->getUser();
    }

    /**
     * Test canceling own nakki booking.
     * Covers lines 51-71.
     */
    public function testNakkiCancelOwnBooking(): void
    {
        ['event' => $event, 'booking' => $booking] = $this->createEventWithNakkikone();

        $user = $this->createVerifiedUser();
        $member = $user->getMember();

        // Assign the booking to this member
        $booking->setMember($member);
        $this->em()->flush();

        $year = (int) $event->getEventDate()->format('Y');
        $cancelPath = \sprintf('/%d/%s/nakkikone/%d/cancel', $year, $event->getUrl(), $booking->getId());
        $referer = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        $this->client->setServerParameter('HTTP_REFERER', $referer);

        $this->client->request('GET', $cancelPath);

        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Cancel should redirect',
        );

        // Verify booking is now unassigned
        $this->em()->clear();
        $refreshedBooking = $this->em()->find(NakkiBooking::class, $booking->getId());
        $this->assertNull($refreshedBooking->getMember(), 'Booking should be unassigned after cancel');
    }

    /**
     * Test nakki signup fails without username.
     * Covers lines 97-102.
     */
    public function testNakkiSignUpFailsWithoutUsername(): void
    {
        ['event' => $event, 'booking' => $booking] = $this->createEventWithNakkikone();

        // Create user without username
        $member = MemberFactory::new()->create([
            'username' => null,
            'emailVerified' => true,
        ]);
        $user = $member->getUser();

        $year = (int) $event->getEventDate()->format('Y');
        $signupPath = \sprintf('/%d/%s/nakkikone/%d/signup', $year, $event->getUrl(), $booking->getId());
        $referer = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        $this->client->setServerParameter('HTTP_REFERER', $referer);

        $this->client->request('GET', $signupPath);

        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Should redirect when username missing',
        );

        // Follow redirect and check for danger flash
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert-danger');
    }

    /**
     * Test nakki signup succeeds with valid user.
     * Covers lines 112-140.
     */
    public function testNakkiSignUpSuccess(): void
    {
        ['event' => $event, 'booking' => $booking] = $this->createEventWithNakkikone(false);

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $signupPath = \sprintf('/%d/%s/nakkikone/%d/signup', $year, $event->getUrl(), $booking->getId());
        $referer = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        $this->client->setServerParameter('HTTP_REFERER', $referer);

        $this->client->request('GET', $signupPath);

        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Signup should redirect',
        );

        // Verify booking is assigned
        $this->em()->clear();
        $refreshedBooking = $this->em()->find(NakkiBooking::class, $booking->getId());
        $this->assertNotNull($refreshedBooking->getMember(), 'Booking should be assigned after signup');
        $this->assertSame($user->getMember()->getId(), $refreshedBooking->getMember()->getId());
    }

    /**
     * Test nakki signup fails when booking already taken.
     * Covers lines 142-145.
     */
    public function testNakkiSignUpFailsWhenAlreadyBooked(): void
    {
        ['event' => $event, 'booking' => $booking] = $this->createEventWithNakkikone();

        // First user takes the booking
        $firstMember = MemberFactory::new()->create(['emailVerified' => true]);
        $booking->setMember($firstMember);
        $this->em()->flush();

        // Second user tries to signup
        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $signupPath = \sprintf('/%d/%s/nakkikone/%d/signup', $year, $event->getUrl(), $booking->getId());
        $referer = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        $this->client->setServerParameter('HTTP_REFERER', $referer);

        $this->client->request('GET', $signupPath);

        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Should redirect when already booked',
        );

        // Follow redirect and check for warning flash
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert-warning');
    }

    /**
     * Test nakki signup fails with overlapping times.
     * Covers lines 115-131.
     */
    public function testNakkiSignUpFailsWithOverlappingTimes(): void
    {
        ['event' => $event, 'nakkikone' => $nakkikone, 'booking' => $booking] = $this->createEventWithNakkikone(true);

        $user = $this->createVerifiedUser();
        $member = $user->getMember();

        // Get the booking's times
        $start = $booking->getStartAt();
        $end = $booking->getEndAt();

        // Create another nakki and booking at the same time and assign to this member
        $definition2 = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Another Nakki',
            'nameEn' => 'Another Nakki EN',
        ]);

        $nakki2 = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'definition' => $definition2,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        $existingBooking = NakkiBookingFactory::new()->create([
            'nakki' => $nakki2,
            'nakkikone' => $nakkikone,
            'startAt' => $start,
            'endAt' => $end,
            'member' => $member,
        ]);

        $year = (int) $event->getEventDate()->format('Y');
        $signupPath = \sprintf('/%d/%s/nakkikone/%d/signup', $year, $event->getUrl(), $booking->getId());
        $referer = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        $this->client->setServerParameter('HTTP_REFERER', $referer);

        $this->client->request('GET', $signupPath);

        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Should redirect when overlapping times',
        );

        // Follow redirect and check for danger flash
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert-danger');
    }

    /**
     * Test nakki signup fails when nakkikone disabled.
     * Covers lines 147-149.
     */
    public function testNakkiSignUpFailsWhenNakkikoneDisabled(): void
    {
        ['event' => $event, 'nakkikone' => $nakkikone, 'booking' => $booking] = $this->createEventWithNakkikone();

        // Disable nakkikone
        $nakkikone->setEnabled(false);
        $this->em()->flush();

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $signupPath = \sprintf('/%d/%s/nakkikone/%d/signup', $year, $event->getUrl(), $booking->getId());
        $referer = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        $this->client->setServerParameter('HTTP_REFERER', $referer);

        $this->client->request('GET', $signupPath);

        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Should redirect when nakkikone disabled',
        );

        // Follow redirect and check for warning flash
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert-warning');
    }

    /**
     * Test responsible page shows nakki info.
     * Covers lines 229-246.
     */
    public function testResponsiblePageShowsNakkiInfo(): void
    {
        ['event' => $event, 'nakkikone' => $nakkikone, 'booking' => $booking] = $this->createEventWithNakkikone();

        $user = $this->createVerifiedUser();
        $member = $user->getMember();

        // Assign booking to member
        $booking->setMember($member);
        $this->em()->flush();

        $year = (int) $event->getEventDate()->format('Y');
        $responsiblePath = \sprintf('/%d/%s/nakkikone/vastuuhenkilo', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $responsiblePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test responsible page with GDPR mode (no responsible nakkis).
     * Covers lines 235-237.
     */
    public function testResponsiblePageGdprMode(): void
    {
        ['event' => $event] = $this->createEventWithNakkikone();

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $responsiblePath = \sprintf('/%d/%s/nakkikone/vastuuhenkilo', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        // User has no responsible nakkis, should fall back to member nakkis (GDPR mode)
        $this->client->request('GET', $responsiblePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test RSVP when system is disabled.
     * Covers lines 270-274.
     */
    public function testRsvpFailsWhenDisabled(): void
    {
        // Enable reboot for POST route matching with site-aware router
        $this->client->enableReboot();

        $event = EventFactory::new()->published()->create([
            'rsvpSystemEnabled' => false,
            'url' => 'rsvp-disabled-'.uniqid('', true),
        ]);

        $member = MemberFactory::new()->create([
            'username' => 'rsvp_user_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);

        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);
    }

    public function testNakkikoneEntityBehaviors(): void
    {
        $eventDate = new \DateTimeImmutable('+7 days');
        $event = new Event();
        $event->setName('Test Event');
        $event->setNimi('Test Event');
        $event->setEventDate($eventDate);
        $event->setUrl('nakki-test-'.uniqid('', true));
        $event->setPublished(true);

        $nakkikone = new Nakkikone($event);

        $definition = new NakkiDefinition();
        $definition->setNameFi('Test Nakki');
        $definition->setNameEn('Test Nakki EN');
        $definition->setDescriptionFi('Test description FI');
        $definition->setDescriptionEn('Test description EN');

        $start = $eventDate->setTime(10, 0);
        $end = $start->modify('+2 hours');

        $nakki = new Nakki();
        $nakki->setDefinition($definition);
        $nakki->setStartAt($start);
        $nakki->setEndAt($end);
        $nakki->setNakkikone($nakkikone);

        $booking = new NakkiBooking();
        $booking->setNakki($nakki);
        $booking->setNakkikone($nakkikone);
        $booking->setStartAt($start);
        $booking->setEndAt($end);

        $member = (new Member())
            ->setEmail('nakki.member@example.test')
            ->setLocale('fi');
        $admin = (new Member())
            ->setEmail('nakki.admin@example.test')
            ->setLocale('fi');
        $otherMember = (new Member())
            ->setEmail('nakki.other@example.test')
            ->setLocale('fi');
        $noBookingMember = (new Member())
            ->setEmail('nakki.nobooking@example.test')
            ->setLocale('fi');

        $nakkikone->setInfoFi('Valitse nakki');
        $nakkikone->setInfoEn('Choose a nakki');
        $nakkikone->setShowLinkInEvent(true);
        $nakkikone->setRequireDifferentTimes(false);
        $nakkikone->setRequiredForTicketReservation(true);
        $nakkikone->addNakki($nakki);

        $nakki->setMattermostChannel('nakki-channel');
        $nakki->setResponsible($member);

        $member->addNakkiBooking($booking);
        $nakkikone->addBooking($booking);
        $nakkikone->addResponsibleAdmin($admin);

        $extraBooking = new NakkiBooking();
        $extraBooking->setNakki($nakki);
        $extraBooking->setMember($otherMember);
        $extraBooking->setStartAt($nakki->getStartAt()->modify('+4 hours'));
        $extraBooking->setEndAt($nakki->getEndAt()->modify('+4 hours'));
        $nakkikone->addBooking($extraBooking);

        self::assertTrue($nakkikone->getNakkis()->contains($nakki));
        self::assertTrue($nakkikone->getBookings()->contains($booking));
        self::assertTrue($nakkikone->getBookings()->contains($extraBooking));

        $nakkikone->addResponsibleAdmin($member);
        $nakkikone->removeResponsibleAdmin($member);
        self::assertFalse($nakkikone->getResponsibleAdmins()->contains($member));

        self::assertSame('Valitse nakki', $nakkikone->getInfoByLocale('fi'));
        self::assertSame('Choose a nakki', $nakkikone->getInfoByLocale('en'));
        self::assertTrue($nakkikone->isShowLinkInEvent());
        self::assertTrue($nakkikone->shouldShowLinkInEvent());
        self::assertFalse($nakkikone->isRequireDifferentTimes());
        self::assertFalse($nakkikone->requiresDifferentTimes());
        self::assertTrue($nakkikone->isRequiredForTicketReservation());

        $memberNakkis = $nakkikone->getMemberNakkis($member);
        $responsibleNakkis = $nakkikone->getResponsibleMemberNakkis($member);
        $adminNakkis = $nakkikone->getResponsibleMemberNakkis($admin);
        $allResponsibles = $nakkikone->getAllResponsibles('fi');

        $name = $nakki->getDefinition()->getName('fi');
        self::assertArrayHasKey($name, $memberNakkis);
        self::assertArrayHasKey($name, $responsibleNakkis);
        self::assertArrayHasKey($name, $adminNakkis);
        self::assertArrayHasKey($name, $allResponsibles);
        self::assertSame('nakki-channel', $allResponsibles[$name]['mattermost']);

        self::assertSame($booking, $nakkikone->ticketHolderHasBooking($member));
        self::assertSame($booking, $nakkikone->ticketHolderHasNakki($member));
        self::assertNull($nakkikone->ticketHolderHasBooking(null));
        self::assertSame($extraBooking, $nakkikone->ticketHolderHasNakki($otherMember));
        self::assertNull($nakkikone->ticketHolderHasBooking($noBookingMember));
        self::assertNull($nakkikone->ticketHolderHasNakki($noBookingMember));

        $nakkikone->setRequiredForTicketReservation(false);
        self::assertNull($nakkikone->ticketHolderHasBooking($member));
        self::assertNull($nakkikone->ticketHolderHasNakki($member));

        $nakkikone->removeBooking($booking);
        self::assertFalse($nakkikone->getBookings()->contains($booking));

        $extraNakki = new Nakki();
        $extraNakki->setDefinition($nakki->getDefinition());
        $extraNakki->setStartAt($nakki->getStartAt()->modify('+3 hours'));
        $extraNakki->setEndAt($nakki->getEndAt()->modify('+3 hours'));
        $nakkikone->addNakki($extraNakki);
        self::assertTrue($nakkikone->getNakkis()->contains($extraNakki));

        $nakkikone->removeNakki($extraNakki);
        self::assertFalse($nakkikone->getNakkis()->contains($extraNakki));

        $nakkikone->removeNakki($nakki);
        self::assertFalse($nakkikone->getNakkis()->contains($nakki));
    }

    /**
     * Test successful RSVP.
     * Covers lines 286-293.
     */
    public function testRsvpSuccess(): void
    {
        // Enable reboot for POST route matching with site-aware router
        $this->client->enableReboot();

        $event = EventFactory::new()->published()->create([
            'rsvpSystemEnabled' => true,
            'url' => 'rsvp-enabled-'.uniqid('', true),
        ]);

        $member = MemberFactory::new()->create([
            'username' => 'rsvp_user_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);

        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);

        // Verify RSVP was created
        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        $this->assertCount(1, $rsvps, 'RSVP should be created');
    }

    /**
     * Test RSVP fails when already RSVPd (via repository check).
     * Covers lines 280-284.
     */
    public function testRsvpFailsWhenAlreadyRsvpd(): void
    {
        // Enable reboot for POST route matching with site-aware router
        $this->client->enableReboot();

        $event = EventFactory::new()->published()->create([
            'rsvpSystemEnabled' => true,
            'url' => 'rsvp-already-'.uniqid('', true),
        ]);

        $member = MemberFactory::new()->create([
            'username' => 'rsvp_user_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        // Create existing RSVP using factory
        RSVPFactory::new()->forEvent($event)->create([
            'member' => $member,
            'firstName' => null,
            'lastName' => null,
            'email' => null,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $path = static::getContainer()->get('router')->generate('entropy_event_rsvp', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);

        $this->client->request('POST', $path);
        $this->assertResponseStatusCodeSame(302);

        // Verify only one RSVP exists (no duplicate created)
        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        $this->assertCount(1, $rsvps, 'Should not create duplicate RSVP');
    }

    /**
     * Test nakkikone page shows flash when disabled.
     * Covers lines 196-198.
     */
    public function testNakkikonePageShowsWarningWhenDisabled(): void
    {
        ['event' => $event, 'nakkikone' => $nakkikone] = $this->createEventWithNakkikone();

        $nakkikone->setEnabled(false);
        $this->em()->flush();

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $nakikonePath = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $nakikonePath);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert-warning');
    }

    /**
     * Test nakkikone page with active member only nakkis.
     * Covers lines 321-332.
     */
    public function testNakkikonePageWithActiveOnlyNakkis(): void
    {
        $now = new \DateTimeImmutable();
        $eventDate = $now->modify('+7 days');

        $event = EventFactory::new()->published()->create([
            'eventDate' => $eventDate,
            'until' => $eventDate->modify('+8 hours'),
            'url' => 'nakki-active-'.uniqid('', true),
        ]);

        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
        ]);

        // Create definition that requires active members
        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Active Only Nakki',
            'nameEn' => 'Active Only Nakki EN',
            'onlyForActiveMembers' => true,
        ]);

        $start = $eventDate->setTime(10, 0);
        $end = $start->modify('+2 hours');

        $nakki = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        NakkiBookingFactory::new()->free()->create([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        // Create active member
        $member = MemberFactory::new()->active()->create([
            'username' => 'active_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);
        $user = $member->getUser();

        $year = (int) $event->getEventDate()->format('Y');
        $nakikonePath = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $nakikonePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test nakkikone page shows nakkis during event (buildNakkiArray coverage).
     * Covers lines 361-368.
     */
    public function testNakkikonePageWithDuringEventNakkis(): void
    {
        $now = new \DateTimeImmutable();
        $eventDate = $now->modify('+7 days')->setTime(12, 0);

        $event = EventFactory::new()->published()->create([
            'eventDate' => $eventDate,
            'until' => $eventDate->modify('+8 hours'),
            'url' => 'nakki-during-'.uniqid('', true),
        ]);

        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
        ]);

        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'During Nakki',
            'nameEn' => 'During Nakki EN',
        ]);

        // Create nakki that starts DURING the event (after eventDate, before until)
        $start = $eventDate->modify('+1 hour');
        $end = $start->modify('+2 hours');

        $nakki = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        NakkiBookingFactory::new()->free()->create([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $nakikonePath = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $nakikonePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test nakkikone page with tear-down nakkis (after event until).
     * Covers line 367.
     */
    public function testNakkikonePageWithTearDownNakkis(): void
    {
        $now = new \DateTimeImmutable();
        $eventDate = $now->modify('+7 days')->setTime(12, 0);
        $until = $eventDate->modify('+6 hours');

        $event = EventFactory::new()->published()->create([
            'eventDate' => $eventDate,
            'until' => $until,
            'url' => 'nakki-teardown-'.uniqid('', true),
        ]);

        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
        ]);

        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Teardown Nakki',
            'nameEn' => 'Teardown Nakki EN',
        ]);

        // Create nakki that starts AFTER the event ends (teardown)
        $start = $until->modify('+1 hour');
        $end = $start->modify('+2 hours');

        $nakki = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        NakkiBookingFactory::new()->free()->create([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $nakikonePath = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $nakikonePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test nakkikone page with multiple bookings (increment not_reserved counter).
     * Covers lines 382-387.
     */
    public function testNakkikonePageWithMultipleFreeBookings(): void
    {
        $now = new \DateTimeImmutable();
        $eventDate = $now->modify('+7 days');

        $event = EventFactory::new()->published()->create([
            'eventDate' => $eventDate,
            'until' => $eventDate->modify('+8 hours'),
            'url' => 'nakki-multi-'.uniqid('', true),
        ]);

        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
        ]);

        $definition = NakkiDefinitionFactory::new()->create([
            'nameFi' => 'Multi Nakki',
            'nameEn' => 'Multi Nakki EN',
        ]);

        $start = $eventDate->setTime(10, 0);
        $end = $start->modify('+2 hours');

        $nakki = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'definition' => $definition,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        // Create multiple free bookings for the same nakki
        NakkiBookingFactory::new()->free()->create([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $start,
            'endAt' => $end,
        ]);

        NakkiBookingFactory::new()->free()->create([
            'nakki' => $nakki,
            'nakkikone' => $nakkikone,
            'startAt' => $start->modify('+2 hours'),
            'endAt' => $end->modify('+2 hours'),
        ]);

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $nakikonePath = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $nakikonePath);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test nakkikone page with event that has no nakkikone.
     * Covers line 311 (getNakkis returns empty array).
     */
    public function testNakkikonePageWithNoNakkikone(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'no-nakkikone-'.uniqid('', true),
        ]);

        // Explicitly ensure no nakkikone
        $event->setNakkikone(null);
        $this->em()->flush();

        $user = $this->createVerifiedUser();

        $year = (int) $event->getEventDate()->format('Y');
        $nakikonePath = \sprintf('/%d/%s/nakkikone', $year, $event->getUrl());

        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', $nakikonePath);

        // Should show page with warning about disabled nakkikone
        $this->assertResponseIsSuccessful();
    }
}
