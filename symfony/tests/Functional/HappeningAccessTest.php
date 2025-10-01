<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Entity\Happening;
use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

/**
 * Functional tests verifying Happening visibility & interactions:
 *
 *  - Public (released) happenings are visible to:
 *      * Their owner
 *      * Other authenticated users
 *  - Private (unreleased) happenings:
 *      * Visible to owner
 *      * Not visible (404) to other users
 *  - Owner can create a new happening on an event that allows member-created happenings.
 *  - Non-owners cannot edit someone else's happening.
 *  - Booking (sign-up) works for a released happening for a different user.
 *
 * Relies on fixtures provided by HappeningTestFixtures & UserFixtures:
 *  - Public Event slug: "happening-event"
 *    - Public Happening slugs: (en) public-happening, (fi) julkinen-happeninki
 *  - Private Event slug: "secret-event"
 *    - Private Happening slugs: (en) secret-happening, (fi) salainen-happeninki
 *
 * NOTE: Routes require authentication (controller guarded by #[IsGranted]).
 */
final class HappeningAccessTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    public function testPublicHappeningAccessibleToOwnerAndAnotherUser(): void
    {
        $publicHappening = $this->findHappeningBySlugEn('public-happening');
        self::assertNotNull($publicHappening, 'Public Happening fixture missing.');
        $event = $publicHappening->getEvent();
        self::assertInstanceOf(Event::class, $event);

        $year = $event->getEventDate()->format('Y');
        $eventSlug = $event->getUrl();
        $happeningSlug = $publicHappening->getSlugEn();

        // Owner user (testuser@example.com)
        $ownerUser = $this->findUserByMemberEmail('testuser@example.com');
        $this->client->loginUser($ownerUser);

        $this->client->request('GET', sprintf('/en/%s/%s/happening/%s', $year, $eventSlug, $happeningSlug));
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Owner should access released (public) happening.'
        );
        $this->assertStringContainsString(
            'Public Happening',
            $this->client->getResponse()->getContent() ?? '',
            'Public happening English name should be visible for owner.'
        );

        // Switch to another authenticated (non-owner) user: admin@example.com
        $adminUser = $this->findUserByMemberEmail('admin@example.com');
        $this->client->loginUser($adminUser);

        $this->client->request('GET', sprintf('/en/%s/%s/happening/%s', $year, $eventSlug, $happeningSlug));
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Another authenticated user should also access released happening.'
        );
    }

    public function testPrivateHappeningOnlyAccessibleToOwner(): void
    {
        $privateHappening = $this->findHappeningBySlugEn('secret-happening');
        self::assertNotNull($privateHappening, 'Private Happening fixture missing.');
        $event = $privateHappening->getEvent();
        self::assertInstanceOf(Event::class, $event);

        $year = $event->getEventDate()->format('Y');
        $eventSlug = $event->getUrl();
        $happeningSlug = $privateHappening->getSlugEn();

        // Owner user (testuser@example.com) should access even if unreleased
        $ownerUser = $this->findUserByMemberEmail('testuser@example.com');
        $this->client->loginUser($ownerUser);

        $this->client->request('GET', sprintf('/en/%s/%s/happening/%s', $year, $eventSlug, $happeningSlug));
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Owner should access their unreleased (private) happening.'
        );
        $this->assertStringContainsString(
            'Secret Happening',
            $this->client->getResponse()->getContent() ?? '',
            'Private happening English name should be visible to owner.'
        );

        // Non-owner user (admin@example.com) should receive 404
        $adminUser = $this->findUserByMemberEmail('admin@example.com');
        $this->client->loginUser($adminUser);

        $this->client->request('GET', sprintf('/en/%s/%s/happening/%s', $year, $eventSlug, $happeningSlug));
        $this->assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'Non-owner should not access unreleased happening (expect 404).'
        );
    }

    public function testOwnerCanAccessCreateFormAndSubmitMinimalHappening(): void
    {
        $publicEvent = $this->em()->getRepository(Event::class)->findOneBy(['url' => 'happening-event']);
        self::assertInstanceOf(Event::class, $publicEvent, 'Public event fixture missing.');
        $year = $publicEvent->getEventDate()->format('Y');

        $ownerUser = $this->findUserByMemberEmail('testuser@example.com');
        $this->client->loginUser($ownerUser);

        $createUrl = sprintf('/en/%s/%s/happening/create', $year, $publicEvent->getUrl());
        $crawler = $this->client->request('GET', $createUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Create form should load for owner.');

        // Ensure core fields present
        $html = $this->client->getResponse()->getContent() ?? '';
        $this->assertStringContainsString('name="happening[nameEn]"', $html);
        $this->assertStringContainsString('name="happening[nameFi]"', $html);

        // Submit minimal valid data (picture omitted; MediaType may make validation soft depending on config)
        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Happening creation form missing.');

        $form = $formNode->form();
        $uniqueSuffix = substr(md5((string) microtime(true)), 0, 6);

        $form['happening[type]'] = 'event';
        $form['happening[time]'] = $publicEvent->getEventDate()->format('Y-m-d H:i:s');
        $form['happening[nameFi]'] = 'Luo FI '.$uniqueSuffix;
        $form['happening[descriptionFi]'] = 'Fi desc';
        $form['happening[paymentInfoFi]'] = '';
        $form['happening[priceFi]'] = '';
        $form['happening[nameEn]'] = 'Create EN '.$uniqueSuffix;
        $form['happening[descriptionEn]'] = 'En desc';
        $form['happening[paymentInfoEn]'] = '';
        $form['happening[priceEn]'] = '';
        if ($form->has('happening[needsPreliminarySignUp]')) {
            $form['happening[needsPreliminarySignUp]']->untick();
        }
        $form['happening[maxSignUps]'] = 0;
        if ($form->has('happening[allowSignUpComments]')) {
            $form['happening[allowSignUpComments]']->tick();
        }
        if ($form->has('happening[needsPreliminaryPayment]')) {
            $form['happening[needsPreliminaryPayment]']->untick();
        }
        if ($form->has('happening[releaseThisHappeningInEvent]')) {
            $form['happening[releaseThisHappeningInEvent]']->tick();
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303], true), 'Successful creation should redirect (got '.$status.').');

        if ($loc = $this->client->getResponse()->headers->get('Location')) {
            $this->client->request('GET', $loc);
            $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Redirect target (show) should load.');
        }
    }

    public function testOwnerCanEditOwnHappeningAndNonOwnerCannot(): void
    {
        $publicHappening = $this->findHappeningBySlugEn('public-happening');
        self::assertNotNull($publicHappening);
        $event = $publicHappening->getEvent();
        $year = $event->getEventDate()->format('Y');

        $ownerUser = $this->findUserByMemberEmail('testuser@example.com');
        $this->client->loginUser($ownerUser);

        $editUrl = sprintf('/en/%s/%s/happening/%s/edit', $year, $event->getUrl(), $publicHappening->getSlugEn());
        $crawler = $this->client->request('GET', $editUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Owner should access edit form.');
        $this->assertGreaterThan(0, $crawler->filter('form')->count(), 'Edit form missing for owner.');

        // Switch to another user and expect redirect (warning) or not 200 if blocked
        $adminUser = $this->findUserByMemberEmail('admin@example.com');
        $this->client->loginUser($adminUser);
        $this->client->request('GET', $editUrl);

        // Either redirected away (3xx) or given 200 but without form (owner check done in controller)
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 302, 303], true),
            'Non-owner edit attempt should not hard error.'
        );
        if (200 === $status) {
            $this->assertStringNotContainsString(
                'name="happening[nameEn]"',
                $this->client->getResponse()->getContent() ?? '',
                'Non-owner should not see full edit form (heuristic check).'
            );
        }
    }

    public function testAnotherUserCanBookPublicHappening(): void
    {
        $publicHappening = $this->findHappeningBySlugEn('public-happening');
        self::assertNotNull($publicHappening);
        $event = $publicHappening->getEvent();
        $year = $event->getEventDate()->format('Y');

        // Use admin user (not owner) to book
        $adminUser = $this->findUserByMemberEmail('admin@example.com');
        $this->client->loginUser($adminUser);

        $showUrl = sprintf('/en/%s/%s/happening/%s', $year, $event->getUrl(), $publicHappening->getSlugEn());
        $crawler = $this->client->request('GET', $showUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Show page should load for booking user.');

        $formNode = $crawler->filter('form[name="happening_booking"]');
        if (0 === $formNode->count()) {
            $formNode = $crawler->filter('form')->first();
        }
        $this->assertGreaterThan(0, $formNode->count(), 'Booking form missing (no form with name=\"happening_booking\" found).');
        $form = $formNode->form();
        if ($form->has('happening_booking[comment]')) {
            $form['happening_booking[comment]'] = 'Looking forward!';
        }

        $this->client->submit($form);

        // After booking expect redirect back or success flash; accept 302 or 303
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303], true), 'Booking submission should redirect (got '.$status.').');
    }

    /**
     * Helper: Find a Happening by English slug.
     */
    private function findHappeningBySlugEn(string $slug): ?Happening
    {
        $repo = $this->em()->getRepository(Happening::class);

        return $repo->findOneBy(['slugEn' => $slug]) ?? null;
    }

    /**
     * Helper: Find a User by the member email (User itself stores email on Member).
     */
    private function findUserByMemberEmail(string $email): User
    {
        $repo = $this->em()->getRepository(User::class);
        /** @var User[] $all */
        $all = $repo->findAll();
        foreach ($all as $u) {
            $m = $u->getMember();
            if ($m && $m->getEmail() === $email) {
                return $u;
            }
        }
        self::fail('User with member email '.$email.' not found in fixtures.');
    }
}
