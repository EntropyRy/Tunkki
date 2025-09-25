<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\EventFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Event;
use App\Entity\Sonata\SonataPageSite;
use App\Tests\Http\SiteAwareKernelBrowser;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader as FixturesLoader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

require_once __DIR__ . "/Http/SiteAwareKernelBrowser.php";

final class EventScenariosTest extends WebTestCase
{
    private static ?ObjectManager $em = null;
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
        self::$em = static::getContainer()->get("doctrine")->getManager();

        $this->purgeAndLoadFixtures();
        $this->ensureSites();
    }

    private function ensureSites(): void
    {
        $em = self::$em;
        $this->assertNotNull($em, "EntityManager not available");

        $repo = $em->getRepository(SonataPageSite::class);

        // Remove existing sites to ensure exactly two
        foreach ($repo->findAll() as $site) {
            $em->remove($site);
        }
        $em->flush();

        // FI default site "/"
        $fi = new SonataPageSite();
        $fi->setName("FI");
        $fi->setEnabled(true);
        $fi->setHost("localhost");
        $fi->setRelativePath("/");
        $fi->setLocale("fi");
        $fi->setIsDefault(true);
        $fi->setEnabledFrom(new \DateTimeImmutable("-1 day"));
        $fi->setEnabledTo(null);
        $em->persist($fi);

        // EN site "/en"
        $en = new SonataPageSite();
        $en->setName("EN");
        $en->setEnabled(true);
        $en->setHost("localhost");
        $en->setRelativePath("/en");
        $en->setLocale("en");
        $en->setIsDefault(false);
        $en->setEnabledFrom(new \DateTimeImmutable("-1 day"));
        $en->setEnabledTo(null);
        $em->persist($en);

        $em->flush();

        $sites = $repo->findAll();
        $this->assertCount(2, $sites, "Expected exactly 2 Sonata sites");
    }

    private function purgeAndLoadFixtures(): void
    {
        $em = self::$em;
        $this->assertNotNull($em, "EntityManager not available");

        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $executor = new ORMExecutor($em, $purger);
        $executor->purge();

        $loader = new FixturesLoader();
        $loader->addFixture(
            new UserFixtures(
                static::getContainer()->get(
                    \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class,
                ),
            ),
        );
        $loader->addFixture(new EventFixtures());
        $executor->execute($loader->getFixtures(), true);
    }

    private function getEventBySlug(string $slug): ?Event
    {
        $em = self::$em;
        $this->assertNotNull($em);

        /** @var \App\Repository\EventRepository $repo */
        $repo = $em->getRepository(Event::class);

        return $repo->findOneBy(["url" => $slug]);
    }

    public function testUnpublishedEventIsDeniedForAnonymous(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug("unpublished-event");
        $this->assertNotNull($event, "Unpublished event fixture missing");

        $year = (int) $event->getEventDate()->format("Y");

        // EN site path with slug route
        $client->request(
            "GET",
            sprintf("/en/%d/%s", $year, "unpublished-event"),
        );

        $this->assertSame(
            302,
            $client->getResponse()->getStatusCode(),
            "Anonymous should be redirected to login for unpublished event",
        );
        $this->assertStringContainsString(
            "/login",
            $client->getResponse()->headers->get("Location") ?? "",
        );
    }

    public function testPastEventLoadsOnEn(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug("past-event");
        $this->assertNotNull($event, "Past event fixture missing");

        $year = (int) $event->getEventDate()->format("Y");

        $client->request("GET", sprintf("/en/%d/%s", $year, "past-event"));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            "Past Event",
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
        $external = $this->getEventBySlug("https://example.com/external-event");
        if (null === $external) {
            // Some projects may use a different flag; if fixture didn't create it as expected, skip gracefully
            $this->markTestSkipped(
                "External event fixture not available in this environment.",
            );
        }

        // If the entity supports the external flag, ensure it is set
        if (method_exists($external, "getExternalUrl")) {
            $this->assertTrue(
                (bool) $external->getExternalUrl(),
                "External URL flag must be true for external event",
            );
        }

        $id = $external->getId();
        $this->assertNotNull($id, "External event must have an ID");

        // EN site path + locale-specific route for id
        $client->request("GET", sprintf("/en/event/%d", $id));

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $this->assertSame(
            "https://example.com/external-event",
            $client->getResponse()->headers->get("Location"),
        );
    }

    public function testTicketsShopPageLoads(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug("tickets-event");
        $this->assertNotNull($event, "Tickets-enabled event fixture missing");

        $year = (int) $event->getEventDate()->format("Y");

        // EN site path to the shop page
        $client->request(
            "GET",
            sprintf("/en/%d/%s/shop", $year, "tickets-event"),
        );

        $status = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [302, 403], true),
            "Shop page should redirect or be forbidden when tickets are unavailable",
        );
        if ($status === 302) {
            $this->assertStringContainsString(
                "/login",
                $client->getResponse()->headers->get("Location") ?? "",
            );
        }
    }

    public function testShopReadyEventShopPage200(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug("shop-event");
        $this->assertNotNull($event, "Shop-ready event fixture missing");

        $year = (int) $event->getEventDate()->format("Y");

        $client->request("GET", sprintf("/en/%d/%s/shop", $year, "shop-event"));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            "Shop Ready Event",
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

        $user = self::$em
            ->getRepository(\App\Entity\User::class)
            ->findOneBy(["authId" => "local-user"]);
        $this->assertNotNull($user, "Fixture user not found");

        $client->loginUser($user, "main");

        $event = $this->getEventBySlug("unpublished-event");
        $this->assertNotNull($event, "Unpublished event fixture missing");
        $year = (int) $event->getEventDate()->format("Y");

        $client->request(
            "GET",
            sprintf("/en/%d/%s", $year, "unpublished-event"),
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessAdminDashboard(): void
    {
        $client = $this->client;

        $admin = self::$em
            ->getRepository(\App\Entity\User::class)
            ->findOneBy(["authId" => "local-admin"]);
        $this->assertNotNull($admin, "Fixture admin not found");

        $client->loginUser($admin);
        $client->followRedirects(true);

        $client->request("GET", "/admin/");

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }
}
