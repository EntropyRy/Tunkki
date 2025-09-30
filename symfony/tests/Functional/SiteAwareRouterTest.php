<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;
use Symfony\Component\Routing\RouterInterface;

/**
 * Functional tests verifying essential behaviors of the custom SiteAwareRouter:
 *
 * Focus Areas:
 *  1. Base route + _locale parameter generation (alias rewriting to suffixed route name).
 *  2. Direct generation of suffixed localized route names (.fi / .en).
 *  3. Structural 404 for wrong-locale path variants (no redirect / no silent fallback).
 *  4. Canonical path shape:
 *       - English: always /en/{year}/{slug}/shop
 *       - Finnish:      /{year}/{slug}/kauppa (no /en prefix)
 *
 * Assumptions (from fixtures & routing):
 *  - Route name base: entropy_event_shop
 *  - Localized route names: entropy_event_shop.fi / entropy_event_shop.en
 *  - A fixture event with slug 'shop-event' exists (see EventFixtures).
 *  - Current (default) site locale is Finnish ('fi'), so base generation without _locale
 *    maps to the Finnish variant.
 */
final class SiteAwareRouterTest extends FixturesWebTestCase
{
    private RouterInterface $router;
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->router = static::getContainer()->get(RouterInterface::class);
        $this->client = new SiteAwareKernelBrowser($kernel);
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    private function params(): array
    {
        // Use a year that matches the fixture event date; we cannot depend on real-time year,
        // so we ask the DB for the event to get its year rather than using date('Y').
        $em = self::$em;
        $event = $em->getRepository(\App\Entity\Event::class)->findOneBy(['url' => 'shop-event']);
        self::assertNotNull($event, 'Fixture event "shop-event" must exist.');
        $year = (int) $event->getEventDate()->format('Y');
        return [
            'year' => $year,
            'slug' => 'shop-event',
        ];
    }

    public function testGenerateBaseRouteFinnishImplicitSiteLocale(): void
    {
        $p = $this->params();
        // No _locale parameter — should adopt site (fi) locale alias automatically.
        $generated = $this->router->generate('entropy_event_shop', $p);
        $expected = sprintf('/%d/%s/kauppa', $p['year'], $p['slug']);
        self::assertSame(
            $expected,
            $generated,
            'Base route without _locale should resolve to Finnish localized path (site default).'
        );
    }

    public function testGenerateBaseRouteWithLocaleParameterFi(): void
    {
        $p = $this->params();
        $generated = $this->router->generate('entropy_event_shop', array_merge($p, ['_locale' => 'fi']));
        $expected = sprintf('/%d/%s/kauppa', $p['year'], $p['slug']);
        self::assertSame(
            $expected,
            $generated,
            'Base route with _locale=fi should map to Finnish path.'
        );
    }

    public function testGenerateBaseRouteWithLocaleParameterEn(): void
    {
        $p = $this->params();
        $generated = $this->router->generate('entropy_event_shop', array_merge($p, ['_locale' => 'en']));
        $expected = sprintf('/en/%d/%s/shop', $p['year'], $p['slug']);
        self::assertSame(
            $expected,
            $generated,
            'Base route with _locale=en should map to English path including /en prefix.'
        );
    }

    public function testGenerateLocalizedSuffixedRoutesDirectly(): void
    {
        $p = $this->params();

        $fi = $this->router->generate('entropy_event_shop.fi', $p);
        $en = $this->router->generate('entropy_event_shop.en', $p);

        self::assertSame(sprintf('/%d/%s/kauppa', $p['year'], $p['slug']), $fi, 'Explicit fi suffixed name should generate Finnish path.');
        self::assertSame(sprintf('/en/%d/%s/shop', $p['year'], $p['slug']), $en, 'Explicit en suffixed name should generate English path.');
    }

    public function testStructural404WrongLocaleVariants(): void
    {
        $p = $this->params();

        $canonicalEn = sprintf('/en/%d/%s/shop', $p['year'], $p['slug']);
        $canonicalFi = sprintf('/%d/%s/kauppa', $p['year'], $p['slug']);

        // Control: canonical pages 200
        $this->client->request('GET', $canonicalFi);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'Canonical Finnish path should be 200.');

        $this->client->request('GET', $canonicalEn);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'Canonical English path should be 200.');

        // Wrong-locale variants:
        // 1. English content without /en prefix -> should 404
        $wrongEn = sprintf('/%d/%s/shop', $p['year'], $p['slug']);
        $this->client->request('GET', $wrongEn);
        self::assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'Unprefixed English variant must structurally 404 (no redirect).'
        );

        // 2. Finnish content forcibly prefixed with /en -> should 404
        $wrongFi = sprintf('/en/%d/%s/kauppa', $p['year'], $p['slug']);
        $this->client->request('GET', $wrongFi);
        self::assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'English-prefixed Finnish variant must structurally 404 (no redirect).'
        );
    }

    public function testCrossLocaleGenerationDoesNotMutateParameters(): void
    {
        $baseParams = $this->params();
        $input = $baseParams;
        $input['_locale'] = 'en';

        $generated = $this->router->generate('entropy_event_shop', $input);
        $expected = sprintf('/en/%d/%s/shop', $baseParams['year'], $baseParams['slug']);

        self::assertSame($expected, $generated, 'Generation with _locale=en must produce English path.');
        // Ensure our original array content (except _locale) is still what we expect.
        self::assertArrayHasKey('_locale', $input, 'Original parameter array should remain intact (local mutation avoided).');
    }
}
