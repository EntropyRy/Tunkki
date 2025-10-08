<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
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
 * Assumptions / Test Data Model:
 *  - Route name base: entropy_event_shop
 *  - Localized route names: entropy_event_shop.fi / entropy_event_shop.en
 *  - Test creates its own event dynamically (no reliance on global fixtures).
 *  - Current (default) site locale is Finnish ('fi'), so base generation without _locale
 *    maps to the Finnish variant.
 */
final class SiteAwareRouterTest extends FixturesWebTestCase
{
    private RouterInterface $router;
    // Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static site-aware client
    private int $year;
    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        // Unified site-aware client initialization to ensure Sonata Page multisite context + SiteRequest wrapping.
        $this->initSiteAwareClient(); // Site-aware client already registered; $this->client resolved via magic __get
        // Prime FI homepage to fix locale-dependent generation defaults
        $this->seedClientHome('fi');
        $this->router = static::getContainer()->get(RouterInterface::class);
        // Create a deterministic (but unique) event for routing tests.
        $this->slug = 'router-'.bin2hex(random_bytes(4));
        $now = new \DateTimeImmutable();
        $event = EventFactory::new()
            ->published()
            ->ticketed()
            ->create([
                'url' => $this->slug,
                'ticketPresaleStart' => $now->modify('-1 day'),
                'ticketPresaleEnd' => $now->modify('+1 day'),
            ]);
        $this->year = (int) $event->getEventDate()->format('Y');
    }

    private function params(): array
    {
        // Return the dynamically created event parameters.
        return [
            'year' => $this->year,
            'slug' => $this->slug,
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
        $status = $this->client->getResponse()->getStatusCode();
        if (in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location');
            if ($loc) {
                $this->client->request('GET', $loc);
            }
        }
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'Canonical Finnish path should be 200.');

        $this->client->request('GET', $canonicalEn);
        $status = $this->client->getResponse()->getStatusCode();
        if (in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location');
            if ($loc) {
                $this->client->request('GET', $loc);
            }
        }
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
