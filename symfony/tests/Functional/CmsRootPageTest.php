<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

/**
 * Verifies canonical root page behavior for CMS (Sonata Page) sites:
 *
 * In the current structural locale model:
 *   - Finnish (default) site root is "/"
 *   - English site root is "/en"
 *   - "/en/" (with trailing slash) should normally normalize/canonicalize
 *     to "/en" (status may be 200 or 301 depending on web server / front controller
 *     trimming logic; we only assert that final canonical content is reachable).
 *   - Wrong-locale cross-access (e.g. attempting to reach English content at "/")
 *     should not produce a redirect to "/en" automatically—Finnish root is distinct.
 *
 * This test focuses strictly on observable HTTP behavior rather than internal Page
 * objects. It assumes:
 *   - SiteFixtures loaded (two sites: fi with relativePath "", en with "/en")
 *   - A CMS root page exists for each site (fixtures or automatic generation)
 */
final class CmsRootPageTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    public function testFinnishRootReturns200(): void
    {
        $this->client->request('GET', '/');
        $status = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            200,
            $status,
            "Finnish root '/' should return 200 (got {$status})."
        );
    }

    public function testEnglishRootReturns200(): void
    {
        $this->client->request('GET', '/en');
        $status = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            200,
            $status,
            "English root '/en' should return 200 (got {$status})."
        );
    }

    public function testEnglishRootWithTrailingSlashCanonicalizes(): void
    {
        $this->client->request('GET', '/en/');
        $firstStatus = $this->client->getResponse()->getStatusCode();

        // If the response is a redirect (301/302) follow once and assert final 200.
        if ($this->client->getResponse()->isRedirection()) {
            $location = $this->client->getResponse()->headers->get('Location') ?? '';
            // Accept both absolute and relative; normalize
            $canonical = preg_replace('#^https?://[^/]+#', '', $location);
            $this->assertSame(
                '/en',
                rtrim($canonical, '/'),
                "Trailing slash English root should redirect to '/en' (got {$canonical})."
            );
            $this->client->request('GET', $canonical);
        }

        $finalStatus = $this->client->getResponse()->getStatusCode();
        $this->assertSame(
            200,
            $finalStatus,
            "Final response for '/en/' canonicalization should be 200 (got {$finalStatus})."
        );
    }

    public function testWrongLocalePatternDoesNotRedirectSilently(): void
    {

        // Simulate a Finnish-only request to English root variant without prefix:
        // There is no assumption of redirect from "/" to "/en".
        $this->client->request('GET', '/');
        $fiStatus = $this->client->getResponse()->getStatusCode();
        $this->assertSame(
            200,
            $fiStatus,
            "Finnish root '/' should remain 200 and not redirect to /en."
        );

        // Conversely, ensure we can still access /en directly (already covered above).
        $this->client->request('GET', '/en');
        $enStatus = $this->client->getResponse()->getStatusCode();
        $this->assertSame(
            200,
            $enStatus,
            "English root '/en' should remain 200 in wrong-locale test scenario."
        );
    }

    public function testLocalizedUrlHelperProducesCanonicalRoots(): void
    {
        static::bootKernel();
        $twig = static::getContainer()->get('twig');
        $requestStack = static::getContainer()->get('request_stack');

        // Simulate Finnish root request context
        $req = \Symfony\Component\HttpFoundation\Request::create('/', 'GET');
        $req->attributes->set('_route', \Sonata\PageBundle\Model\PageInterface::PAGE_ROUTE_CMS_NAME);
        $requestStack->push($req);

        /** @var \Twig\TemplateWrapper $template */
        $template = $twig->createTemplate(
            "{{ localized_url('fi') }}|{{ localized_url('en') }}"
        );

        $rendered = $template->render([]);
        [$fiRoot, $enRoot] = explode('|', $rendered);

        $this->assertSame('/', $fiRoot, "localized_url('fi') should produce '/' for Finnish root.");
        $this->assertSame('/en', $enRoot, "localized_url('en') should produce '/en' for English root.");
    }
}
