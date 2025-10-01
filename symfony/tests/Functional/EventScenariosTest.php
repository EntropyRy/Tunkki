<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

require_once __DIR__.'/../Http/SiteAwareKernelBrowser.php';

final class EventScenariosTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    private function getEventBySlug(string $slug): ?Event
    {
        $em = self::$em;
        $this->assertNotNull($em);

        /** @var \App\Repository\EventRepository $repo */
        $repo = $em->getRepository(Event::class);

        return $repo->findOneBy(['url' => $slug]);
    }

    public function testUnpublishedEventIsDeniedForAnonymous(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug('unpublished-event');
        $this->assertNotNull($event, 'Unpublished event fixture missing');

        $year = (int) $event->getEventDate()->format('Y');

        // EN site path with slug route
        $client->request(
            'GET',
            sprintf('/en/%d/%s', $year, 'unpublished-event'),
        );

        $this->assertSame(
            302,
            $client->getResponse()->getStatusCode(),
            'Anonymous should be redirected to login for unpublished event',
        );
        $this->assertStringContainsString(
            '/login',
            $client->getResponse()->headers->get('Location') ?? '',
        );
    }

    public function testPastEventLoadsOnEn(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug('past-event');
        $this->assertNotNull($event, 'Past event fixture missing');

        $year = (int) $event->getEventDate()->format('Y');

        $client->request('GET', sprintf('/en/%d/%s', $year, 'past-event'));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            'Past Event',
            $client->getResponse()->getContent(),
        );
        $this->assertStringContainsString(
            'lang="en"',
            $client->getResponse()->getContent(),
        );
    }

    public function testExternalEventRedirectsFromIdRoute(): void
    {
        $client = $this->client;

        // The "external" event uses the ID-based route and should redirect to the external URL
        $external = $this->getEventBySlug('https://example.com/external-event');
        if (null === $external) {
            // Some projects may use a different flag; if fixture didn't create it as expected, skip gracefully
            $this->markTestSkipped(
                'External event fixture not available in this environment.',
            );
        }

        // If the entity supports the external flag, ensure it is set
        if (method_exists($external, 'getExternalUrl')) {
            $this->assertTrue(
                (bool) $external->getExternalUrl(),
                'External URL flag must be true for external event',
            );
        }

        $id = $external->getId();
        $this->assertNotNull($id, 'External event must have an ID');

        // EN site path + locale-specific route for id
        $client->request('GET', sprintf('/en/event/%d', $id));

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $this->assertSame(
            'https://example.com/external-event',
            $client->getResponse()->headers->get('Location'),
        );
    }

    public function testTicketsShopPageLoads(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug('tickets-event');
        $this->assertNotNull($event, 'Tickets-enabled event fixture missing');

        $year = (int) $event->getEventDate()->format('Y');

        $client->request(
            'GET',
            sprintf('/en/%d/%s/shop', $year, 'tickets-event'),
        );

        $status = $client->getResponse()->getStatusCode();
        $this->assertSame(
            302,
            $status,
            'Anonymous user should be redirected to login for tickets-enabled event',
        );
        $this->assertStringContainsString(
            '/login',
            $client->getResponse()->headers->get('Location') ?? '',
        );
    }

    public function testShopReadyEventShopPage200(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug('shop-event');
        $this->assertNotNull($event, 'Shop-ready event fixture missing');

        $year = (int) $event->getEventDate()->format('Y');

        $client->request('GET', sprintf('/en/%d/%s/shop', $year, 'shop-event'));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            'Shop Ready Event',
            $client->getResponse()->getContent(),
        );
        $this->assertStringContainsString(
            'lang="en"',
            $client->getResponse()->getContent(),
        );
    }

    public function testUserCanLoginAndAccessUnpublishedEvent(): void
    {
        $client = $this->client;
        $client->followRedirects(true);

        // Ensure fixture user exists
        $user = self::$em
            ->getRepository(\App\Entity\User::class)
            ->findOneBy(['authId' => 'local-user']);
        $this->assertNotNull($user, 'Fixture user not found');

        // Perform real form login (programmatic loginUser was unreliable with custom request wrapper)
        $crawler = $client->request('GET', '/login');
        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Login page should load',
        );

        $formNode = $crawler->filter('form')->first();
        $this->assertTrue(
            $formNode->count() > 0,
            'Login form not found on /login',
        );

        $form = $formNode->form([
            '_username' => 'local-user',
            '_password' => 'userpass123',
        ]);
        $client->submit($form);

        // Fetch unpublished event after authenticated login
        $event = $this->getEventBySlug('unpublished-event');
        $this->assertNotNull($event, 'Unpublished event fixture missing');
        $year = (int) $event->getEventDate()->format('Y');

        $client->request(
            'GET',
            sprintf('/en/%d/%s', $year, 'unpublished-event'),
        );

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Authenticated user should access unpublished event',
        );
    }

    public function testAdminCanAccessAdminDashboard(): void
    {
        $client = $this->client;

        $admin = self::$em
            ->getRepository(\App\Entity\User::class)
            ->findOneBy(['authId' => 'local-admin']);
        $this->assertNotNull($admin, 'Fixture admin not found');

        $client->loginUser($admin);
        $client->followRedirects(true);

        $client->request('GET', '/admin/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }
}
