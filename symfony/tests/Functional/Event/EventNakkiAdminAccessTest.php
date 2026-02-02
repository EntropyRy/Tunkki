<?php

declare(strict_types=1);

namespace App\Tests\Functional\Event;

use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkiDefinitionFactory;
use App\Factory\NakkikoneFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * Tests access control for the Nakki admin interface.
 *
 * Validates that:
 * - EventNakkiAdminVoter properly restricts access to authorized users only
 * - ROLE_ADMIN and ROLE_SUPER_ADMIN can access
 * - Event nakki responsible admins can access
 * - Nakki responsibles can access
 * - Regular users are denied
 * - Anonymous users are redirected to login
 * - Works in both locales (FI unprefixed, EN /en/ prefixed)
 */
final class EventNakkiAdminAccessTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function newClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return $this->client;
    }

    /* -----------------------------------------------------------------
     * Positive: ROLE_ADMIN can access
     * ----------------------------------------------------------------- */
    public function testAdminCanAccessNakkiAdmin(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        // Should render the Board component
        $this->client->assertSelectorExists('[data-controller*="scroll-planner"]');
    }

    public function testAdminCanAccessNakkiAdminEnglishLocale(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-en-'.uniqid('', true),
        ]);

        [$_admin, $client] = $this->loginAsRole('ROLE_ADMIN');

        $year = $event->getEventDate()->format('Y');
        $path = "/en/{$year}/{$event->getUrl()}/nakkikone/admin";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorExists('[data-controller*="scroll-planner"]');
    }

    /* -----------------------------------------------------------------
     * Positive: ROLE_SUPER_ADMIN can access
     * ----------------------------------------------------------------- */
    public function testSuperAdminCanAccessNakkiAdmin(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-super-'.uniqid('', true),
        ]);

        [$_super, $client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
    }

    /* -----------------------------------------------------------------
     * Positive: Event nakki responsible admin can access
     * ----------------------------------------------------------------- */
    public function testNakkiResponsibleAdminCanAccess(): void
    {
        $member = MemberFactory::new()->active()->create([
            'email' => 'member_'.uniqid('', true).'@example.test',
        ]);
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-resp-admin-'.uniqid('', true),
        ]);
        $member = $this->em()
            ->getRepository(\App\Entity\Member::class)
            ->find($member->getId());
        \assert($member instanceof \App\Entity\Member);
        $event = $this->em()
            ->getRepository(\App\Entity\Event::class)
            ->find($event->getId());
        \assert($event instanceof \App\Entity\Event);
        $nakkikone = new \App\Entity\Nakkikone($event);
        $event->setNakkikone($nakkikone);
        $nakkikone->addResponsibleAdmin($member);
        $this->em()->persist($nakkikone);
        $this->em()->flush();

        $this->em()->clear();
        $event = $this->em()->getRepository(\App\Entity\Event::class)->find($event->getId());
        \assert($event instanceof \App\Entity\Event);
        $member = $this->em()->getRepository(\App\Entity\Member::class)->find($member->getId());
        \assert($member instanceof \App\Entity\Member);

        // Login using the member's user email
        $user = $member->getUser();
        \assert($user instanceof \App\Entity\User);
        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
    }

    /* -----------------------------------------------------------------
     * Positive: Nakki responsible can access
     * ----------------------------------------------------------------- */
    public function testNakkiResponsibleCanAccess(): void
    {
        $member = MemberFactory::new()->active()->create([
            'email' => 'member_'.uniqid('', true).'@example.test',
        ]);
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-nakki-resp-'.uniqid('', true),
        ]);
        $definition = NakkiDefinitionFactory::new()->create();
        $member = $this->em()
            ->getRepository(\App\Entity\Member::class)
            ->find($member->getId());
        \assert($member instanceof \App\Entity\Member);
        $event = $this->em()
            ->getRepository(\App\Entity\Event::class)
            ->find($event->getId());
        \assert($event instanceof \App\Entity\Event);
        $definition = $this->em()
            ->getRepository(\App\Entity\NakkiDefinition::class)
            ->find($definition->getId());
        \assert($definition instanceof \App\Entity\NakkiDefinition);
        $nakkikone = new \App\Entity\Nakkikone($event);
        $event->setNakkikone($nakkikone);
        self::assertCount(
            0,
            $nakkikone->getResponsibleAdmins(),
            'Expected no responsible admins before flush.',
        );
        $startAt = new \DateTimeImmutable('+1 day 10:00');
        $endAt = $startAt->modify('+8 hours');
        $nakki = new \App\Entity\Nakki();
        $nakki
            ->setNakkikone($nakkikone)
            ->setDefinition($definition)
            ->setResponsible($member)
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setNakkiInterval(new \DateInterval('PT1H'));
        $this->em()->persist($nakki);
        $this->em()->persist($nakkikone);
        $this->em()->flush();

        $this->em()->clear();
        $event = $this->em()->getRepository(\App\Entity\Event::class)->find($event->getId());
        \assert($event instanceof \App\Entity\Event);
        $member = $this->em()->getRepository(\App\Entity\Member::class)->find($member->getId());
        \assert($member instanceof \App\Entity\Member);

        // Login using the member's user email
        $user = $member->getUser();
        \assert($user instanceof \App\Entity\User);
        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
    }

    /* -----------------------------------------------------------------
     * Negative: Regular user denied
     * ----------------------------------------------------------------- */
    public function testRegularUserDeniedAccess(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-denied-'.uniqid('', true),
        ]);

        $email = 'regular-'.bin2hex(random_bytes(4)).'@example.com';
        [$_regular, $client] = $this->loginAsEmail($email);

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        $response = $client->getResponse();

        // Should get 403 Forbidden for authenticated but unauthorized user
        self::assertSame(403, $response->getStatusCode(), 'Regular user should get 403 Forbidden');
    }

    public function testRegularUserDeniedAccessEnglishLocale(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-denied-en-'.uniqid('', true),
        ]);

        $email = 'regular-en-'.bin2hex(random_bytes(4)).'@example.com';
        [$_regular, $client] = $this->loginAsEmail($email);

        $year = $event->getEventDate()->format('Y');
        $path = "/en/{$year}/{$event->getUrl()}/nakkikone/admin";

        $client->request('GET', $path);
        $response = $client->getResponse();

        self::assertSame(403, $response->getStatusCode(), 'Regular user should get 403 Forbidden (EN)');
    }

    public function testRegularUserDeniedWhenNoResponsiblesConfigured(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-no-resp-'.uniqid('', true),
        ]);
        $nakkikone = NakkikoneFactory::new()->enabled()->create([
            'event' => $event,
        ]);
        NakkiFactory::new()->with([
            'nakkikone' => $nakkikone,
            'responsible' => null,
        ])->create();

        $email = 'regular-no-resp-'.bin2hex(random_bytes(4)).'@example.com';
        [$_regular, $client] = $this->loginAsEmail($email);

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $client->request('GET', $path);
        $response = $client->getResponse();

        self::assertSame(403, $response->getStatusCode(), 'Regular user should get 403 Forbidden without responsibles');
    }

    /* -----------------------------------------------------------------
     * Negative: Anonymous user redirected to login
     * ----------------------------------------------------------------- */
    public function testAnonymousUserRedirectedToLogin(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-anon-'.uniqid('', true),
        ]);

        $year = $event->getEventDate()->format('Y');
        $path = "/{$year}/{$event->getUrl()}/nakkikone/hallinta";

        $this->client->request('GET', $path);

        // Should redirect to login
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect(), 'Anonymous user should be redirected');

        $location = $response->headers->get('Location') ?? '';
        self::assertMatchesRegularExpression('#/login(/|$)#', $location, 'Should redirect to login page');
    }

    public function testAnonymousUserRedirectedToLoginEnglishLocale(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-anon-en-'.uniqid('', true),
        ]);

        $year = $event->getEventDate()->format('Y');
        $path = "/en/{$year}/{$event->getUrl()}/nakkikone/admin";

        $this->client->request('GET', $path);

        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect(), 'Anonymous user should be redirected (EN)');

        $location = $response->headers->get('Location') ?? '';
        self::assertMatchesRegularExpression('#/login(/|$)#', $location, 'Should redirect to login page (EN)');
    }
}
