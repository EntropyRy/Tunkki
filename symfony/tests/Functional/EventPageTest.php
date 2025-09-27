<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Tests\Http\SiteAwareKernelBrowser;
use App\Tests\_Base\FixturesWebTestCase;

require_once __DIR__ . "/../Http/SiteAwareKernelBrowser.php";

final class EventPageTest extends FixturesWebTestCase
{
    private const TEST_SLUG = "test-event";

    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
    }

    public function testEventPageLoadsBySlugForFi(): void
    {
        $client = $this->client;
        $event = $this->getEventBySlug(self::TEST_SLUG);
        $this->assertNotNull($event, "Expected test Event to exist");

        $year = (int) $event->getEventDate()->format("Y");

        $client->request("GET", sprintf("/%d/%s", $year, self::TEST_SLUG));

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

        $client->request("GET", sprintf("/en/%d/%s", $year, self::TEST_SLUG));

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
