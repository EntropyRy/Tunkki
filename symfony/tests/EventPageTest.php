<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Event;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader as FixturesLoader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sonata\PageBundle\Request\SiteRequest;
use App\Entity\Sonata\SonataPageSite;
require_once __DIR__ . "/Http/SiteAwareKernelBrowser.php";

final class EventPageTest extends WebTestCase
{
    private const TEST_SLUG = "test-event";

    private static ?ObjectManager $em = null;
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
        self::$em = static::getContainer()->get("doctrine")->getManager();
        $this->purgeAndLoadFixtures();
    }

    private function purgeAndLoadFixtures(): void
    {
        $em = self::$em;
        $this->assertNotNull($em);

        // Always start with a clean DB for this test
        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $executor = new ORMExecutor($em, $purger);
        $executor->purge();

        // Ensure there are exactly two Sonata sites: FI default (/) and EN (/en)
        $siteRepo = $em->getRepository(SonataPageSite::class);
        foreach ($siteRepo->findAll() as $existingSite) {
            $em->remove($existingSite);
        }
        $em->flush();

        $fiSite = new SonataPageSite();
        $fiSite->setName("FI");
        $fiSite->setEnabled(true);
        $fiSite->setHost("localhost");
        $fiSite->setRelativePath("/");
        $fiSite->setLocale("fi");
        $fiSite->setIsDefault(true);
        $fiSite->setEnabledFrom(new \DateTimeImmutable("-1 day"));
        $fiSite->setEnabledTo(null);
        $em->persist($fiSite);

        $enSite = new SonataPageSite();
        $enSite->setName("EN");
        $enSite->setEnabled(true);
        $enSite->setHost("localhost");
        $enSite->setRelativePath("/en");
        $enSite->setLocale("en");
        $enSite->setIsDefault(false);
        $enSite->setEnabledFrom(new \DateTimeImmutable("-1 day"));
        $enSite->setEnabledTo(null);
        $em->persist($enSite);

        $em->flush();

        // Assert exactly two sites exist with expected locales and paths
        $sites = $siteRepo->findAll();
        $this->assertCount(
            2,
            $sites,
            "Expected exactly two Sonata sites (FI and EN)",
        );
        $locales = array_map(fn($s) => $s->getLocale(), $sites);
        sort($locales);
        $this->assertSame(["en", "fi"], $locales);

        // Map by locale for precise checks
        $byLocale = [];
        foreach ($sites as $s) {
            $byLocale[$s->getLocale()] = $s;
        }
        $this->assertSame("/", $byLocale["fi"]->getRelativePath());
        $this->assertSame("/en", $byLocale["en"]->getRelativePath());
        $this->assertSame("localhost", $byLocale["fi"]->getHost());
        $this->assertSame("localhost", $byLocale["en"]->getHost());
        $this->assertTrue($byLocale["fi"]->getIsDefault());

        $loader = new FixturesLoader();

        // Prefer unified EventFixtures if present; otherwise create the entity inline.
        if (class_exists(\App\DataFixtures\EventFixtures::class)) {
            $loader->addFixture(new \App\DataFixtures\EventFixtures());
        } else {
            $loader->addFixture(
                new class extends \Doctrine\Bundle\FixturesBundle\Fixture {
                    public function load(
                        \Doctrine\Persistence\ObjectManager $manager,
                    ): void {
                        $event = new Event();
                        $event->setName("Test Event");
                        $event->setNimi("Testitapahtuma");
                        $event->setType("event");
                        $event->setPublishDate(
                            new \DateTimeImmutable("-1 hour"),
                        );
                        $dt = new \DateTimeImmutable("+1 day");
                        $dt = $dt->setTime(20, 0);
                        $event->setEventDate($dt);
                        $event->setPublished(true);
                        $event->setUrl(EventPageTest::TEST_SLUG);
                        $event->setTemplate("event.html.twig");
                        $event->setContent(
                            "<p>Test content for the event (EN)</p>",
                        );
                        $event->setSisallys(
                            "<p>Testisisältö tapahtumalle (FI)</p>",
                        );

                        $manager->persist($event);
                        $manager->flush();
                    }
                },
            );
        }

        $executor->execute($loader->getFixtures(), true);
    }

    public function testEventPageLoadsBySlugForFi(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug(self::TEST_SLUG);
        $this->assertNotNull($event, "Expected test Event to exist");

        $year = (int) $event->getEventDate()->format("Y");

        // Request using FI locale; route is the same for both locales
        $client->request(
            "GET",
            sprintf("/%d/%s", $year, self::TEST_SLUG),
            [],
            [],
            [],
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            "Testitapahtuma",
            $client->getResponse()->getContent(),
        );
        $this->assertStringContainsString(
            'lang="fi"',
            $client->getResponse()->getContent(),
        );
    }

    public function testEventPageLoadsBySlugForEn(): void
    {
        $client = $this->client;

        $event = $this->getEventBySlug(self::TEST_SLUG);
        $this->assertNotNull($event, "Expected test Event to exist");

        $year = (int) $event->getEventDate()->format("Y");

        // Request using EN locale
        $client->request(
            "GET",
            sprintf("/en/%d/%s", $year, self::TEST_SLUG),
            [],
            [],
            [],
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            "Test Event",
            $client->getResponse()->getContent(),
        );
        $this->assertStringContainsString(
            'lang="en"',
            $client->getResponse()->getContent(),
        );
    }

    private function getEventBySlug(string $slug): ?Event
    {
        $em = self::$em;
        $this->assertNotNull($em);

        /** @var \App\Repository\EventRepository $repo */
        $repo = $em->getRepository(Event::class);

        return $repo->findOneBy(["url" => $slug]);
    }
}
