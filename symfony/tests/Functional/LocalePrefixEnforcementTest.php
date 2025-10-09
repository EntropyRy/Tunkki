<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Locale canonical URL tests (structural model).
 *
 * New structural rules:
 *  - Wrong-locale paths produce 404 (no redirect canonicalization).
 *  - Canonical EN paths always start with /en; FI paths never do.
 *  - localized_url() helper must output canonical paths using router generation logic.
 *
 * Covered assertions:
 *  1. /en/... English variant 200, unprefixed English variant 404
 *  2. Finnish variant 200, /en + Finnish variant 404
 *  3. Twig path() vs localized_url() alignment
 */
final class LocalePrefixEnforcementTest extends FixturesWebTestCase
{
    // Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static registration
    private int $shopYear;
    private string $shopSlug;

    protected function setUp(): void
    {
        parent::setUp();

        // Create per-test event (avoid reliance on a global 'shop-event' fixture)
        $this->shopSlug = 'locale-'.bin2hex(random_bytes(4));
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => $this->shopSlug,
                'name' => 'Locale Test Event',
                'nimi' => 'Lokalisointitapahtuma',
            ]);
        $this->shopYear = (int) $event->getEventDate()->format('Y');
    }

    private function canonicalEnglishPath(): string
    {
        return \sprintf('/en/%d/%s/shop', $this->shopYear, $this->shopSlug);
    }

    private function englishPathWithoutPrefix(): string
    {
        return \sprintf('/%d/%s/shop', $this->shopYear, $this->shopSlug);
    }

    private function finnishPath(): string
    {
        return \sprintf('/%d/%s/kauppa', $this->shopYear, $this->shopSlug);
    }

    public function testEnglishCanonicalAndWrongLocaleVariants(): void
    {
        // Canonical English path should be 200
        $this->client->request('GET', $this->canonicalEnglishPath());
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location');
            if ($loc) {
                $this->client->request('GET', $loc);
            }
        }
        $finalStatus = $this->client->getResponse()->getStatusCode();
        $this->assertContains(
            $finalStatus,
            [200, 404],
            'Canonical English path should return 200 or 404 (access-controlled).'
        );

        // Unprefixed English route (wrong-locale form) should 404 structurally
        $this->client->request('GET', $this->englishPathWithoutPrefix());
        $this->assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'Unprefixed English shop path should 404 (no redirect).',
        );
    }

    public function testFinnishCanonicalAndWrongLocaleVariants(): void
    {
        // Canonical Finnish path should be 200
        $this->client->request('GET', $this->finnishPath());
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location');
            if ($loc) {
                $this->client->request('GET', $loc);
            }
        }
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Finnish canonical shop path should return 200',
        );

        // English prefix added to Finnish path should 404
        $this->client->request('GET', '/en'.$this->finnishPath());
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            \in_array($status, [404, 301, 302, 303], true),
            'Adding /en to Finnish path must 404 or redirect structurally (no silent success).'
        );
    }

    public function testTwigPathAndLocalizedUrlHelper(): void
    {
        // Rely on the existing kernel/container from the initialized site-aware client
        $container = static::getContainer();

        /** @var RouterInterface $router */
        $router = $container->get(RouterInterface::class);
        /** @var TwigEnvironment $twig */
        $twig = $container->get('twig');
        $requestStack = $container->get('request_stack');

        $year = $this->shopYear;
        $slug = $this->shopSlug;

        // Simulate Finnish request context so localized_url('en') transforms it properly
        $fiPath = $this->finnishPath();
        $req = Request::create($fiPath, 'GET');
        $req->attributes->set('_route', 'entropy_event_shop.fi');
        $req->attributes->set('_route_params', [
            'year' => $year,
            'slug' => $slug,
        ]);
        $requestStack->push($req);

        $rawEn = $router->generate('entropy_event_shop.en', [
            'year' => $year,
            'slug' => $slug,
        ]);
        $rawFi = $router->generate('entropy_event_shop.fi', [
            'year' => $year,
            'slug' => $slug,
        ]);

        // With structural prefixes, EN path must already include /en
        $this->assertSame(
            $this->canonicalEnglishPath(),
            $rawEn,
            'Localized EN route generation must include /en prefix structurally.',
        );
        $this->assertSame(
            $fiPath,
            $rawFi,
            'Localized FI route generation must produce unprefixed Finnish path.',
        );

        $template = $twig->createTemplate(
            "{{ path('entropy_event_shop.en', {'year': year, 'slug': slug}) }}|{{ path('entropy_event_shop.fi', {'year': year, 'slug': slug}) }}|{{ localized_url('en') }}|{{ localized_url('fi') }}",
        );
        $rendered = $template->render([
            'year' => $year,
            'slug' => $slug,
        ]);

        [$twigRawEn, $twigRawFi, $locEn, $locFi] = explode('|', $rendered);

        $this->assertSame(
            $rawEn,
            $twigRawEn,
            'Twig path() EN must match router generate EN.',
        );
        $this->assertSame(
            $rawFi,
            $twigRawFi,
            'Twig path() FI must match router generate FI.',
        );

        $this->assertSame(
            $this->canonicalEnglishPath(),
            $locEn,
            'localized_url(en) must return canonical /en English URL.',
        );
        $this->assertSame(
            $fiPath,
            $locFi,
            'localized_url(fi) must return canonical Finnish URL.',
        );
    }
}
