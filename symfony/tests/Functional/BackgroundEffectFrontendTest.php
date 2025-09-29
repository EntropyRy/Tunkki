<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

require_once __DIR__ . "/../Http/SiteAwareKernelBrowser.php";

/**
 * Functional test to verify that a background effect configured on an Event
 * is rendered in the frontend after the removal of BackgroundEffectConfigProvider.
 *
 * What we assert:
 *  - Event page returns 200.
 *  - The expected <canvas> tag with id=<effect> is present.
 *  - The data-config attribute contains exactly the JSON we persisted.
 *  - The importmap block includes the effect module name (string match heuristic).
 *
 * NOTE:
 *  This test does not attempt to execute JS or validate the animation itself;
 *  it focuses on server-rendered HTML integration points required for the
 *  front-end effect scripts to bootstrap correctly.
 */
final class BackgroundEffectFrontendTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Follow same pattern as other functional tests to avoid double kernel boot issues.
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
    }

    public function testFlowfieldsEffectCanvasRendersWithConfig(): void
    {
        $em = $this->em();

        // Create a dedicated event with a background effect.
        $event = new Event();
        $event
            ->setName("Flowfields Front Test EN")
            ->setNimi("Flowfields Front Test FI")
            ->setType("event")
            ->setEventDate(new \DateTimeImmutable("+2 days"))
            ->setPublishDate(new \DateTimeImmutable("-1 hour"))
            ->setPublished(true)
            ->setUrl("flowfields-front-test")
            ->setTemplate("event.html.twig")
            ->setBackgroundEffect("flowfields")
            ->setBackgroundEffectOpacity(65)
            ->setBackgroundEffectPosition("z-index:0;");

        // Intentionally unformatted JSON (verbatim persistence already tested separately)
        $rawConfig =
            '{"particleCount":123,"particleBaseSpeed":1.1,"nested":{"x":5},"array":[3,2,1]}';
        $event->setBackgroundEffectConfig($rawConfig);

        $em->persist($event);
        $em->flush();
        $id = $event->getId();
        $this->assertNotNull($id, "Persisted event must have ID.");

        $year = (int) $event->getEventDate()->format("Y");

        // Request the English localized slug route (pattern used in other tests)
        $client = $this->client;
        $this->assertNotNull($client, "Client should be initialized in setUp.");
        $client->request(
            "GET",
            sprintf("/en/%d/%s", $year, "flowfields-front-test"),
        );

        $response = $client->getResponse();
        $this->assertSame(
            200,
            $response->getStatusCode(),
            "Event page should return 200.",
        );

        $html = $response->getContent();
        $this->assertIsString($html);

        // Assert the canvas element with id="flowfields" exists
        $this->assertStringContainsString(
            'id="flowfields"',
            $html,
            "Canvas with id=flowfields should be present for the effect.",
        );

        // Assert data-config attribute contains our raw JSON (must appear verbatim)
        $this->assertStringContainsString(
            htmlspecialchars($rawConfig, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"),
            $html,
            "Rendered data-config must contain the exact raw JSON.",
        );

        // Heuristic: importmap inclusion (module name appears somewhere in the HTML)
        // Depending on the importmap implementation, it might appear in a <script type="importmap"> JSON.
        $this->assertStringContainsString(
            "flowfields",
            $html,
            "Importmap (or script tags) should reference the flowfields module.",
        );

        // Opacity style check (65% -> 0.65)
        $this->assertStringContainsString(
            "opacity: 0.65",
            $html,
            "Canvas style should include computed opacity based on backgroundEffectOpacity.",
        );
    }
}
