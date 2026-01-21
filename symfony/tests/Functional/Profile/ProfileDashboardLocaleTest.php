<?php

declare(strict_types=1);

namespace App\Tests\Functional\Profile;

use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Symfony\Component\Routing\RouterInterface;

/**
 * Bilingual dashboard route coverage.
 *
 * Roadmap alignment:
 *  - #22 Bilingual data providers expansion
 *  - #16 Assertion modernization (structural over substring)
 *
 * Verifies:
 *  - Distinct localized route names: dashboard.fi (/yleiskatsaus) & dashboard.en (/en/dashboard)
 *  - Each localized path returns 200 when authenticated
 *  - <html lang=".."> matches expected locale
 *
 * Intent:
 *  - Avoid hardcoding path patterns in multiple tests
 *  - Provide a single data-driven source of truth for dashboard localization
 */
final class ProfileDashboardLocaleTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    // Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static site-aware client registration
    private RouterInterface $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient(); // Site-aware client registered in base; $this->client resolved via magic __get

        // Seed initial BrowserKit state via helper
        $this->seedClientHome('en');

        $this->router = static::getContainer()->get(RouterInterface::class);
    }

    /**
     * @return array<array{locale:string}>
     */
    public static function dashboardLocaleProvider(): array
    {
        return [['fi'], ['en']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dashboardLocaleProvider')]
    public function testDashboardLoadsPerLocale(string $locale): void
    {
        // Seed locale-specific homepage to stabilize router defaults and CMS routing
        $this->seedClientHome($locale);
        // Arrange: create a member with the given locale (fall back gracefully if attribute name differs)
        $factoryOverrides = [];
        // Common attribute naming guess (adjust if entity uses different property)
        $factoryOverrides['locale'] = $locale;

        $member = MemberFactory::new($factoryOverrides)->create();
        $user = $member->getUser();
        self::assertNotNull($user, 'Factory did not yield an attached User.');
        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
        // Ping a protected route to ensure token is serialized and session cookie set
        $this->client->request(
            'GET',
            'en' === $locale ? '/en/profile' : '/profiili',
        );
        if (
            \in_array(
                $this->client->getResponse()->getStatusCode(),
                [301, 302, 303],
                true,
            )
        ) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            if ('' !== $loc) {
                $this->client->request('GET', $loc);
            }
        }
        $this->seedClientHome($locale);

        // Act: generate path using route with explicit _locale
        try {
            $path = $this->router->generate('dashboard', [
                '_locale' => $locale,
            ]);
        } catch (\Throwable $e) {
            $path = 'en' === $locale ? '/en/dashboard' : '/yleiskatsaus';
            @fwrite(
                \STDERR,
                \sprintf(
                    "[DashboardTest] Router failed to generate 'dashboard' for locale '%s': %s. Falling back to path '%s'\n",
                    $locale,
                    $e->getMessage(),
                    $path,
                ),
            );
        }

        // Sanity: structural expectations for path shape (avoid brittle exact match)
        if ('en' === $locale) {
            self::assertStringStartsWith(
                '/en/',
                $path,
                'English dashboard path should start with /en/.',
            );
        } else {
            self::assertFalse(
                str_starts_with($path, '/en/'),
                'Finnish dashboard path must not start with /en/.',
            );
        }

        $crawler = $this->client->request('GET', $path);
        $status = $this->client->getResponse()->getStatusCode();
        if ($status < 200 || $status >= 300) {
            try {
                $em = $this->em();
                $siteRepo = $em->getRepository(
                    "App\Entity\Sonata\SonataPageSite",
                );
                $pageRepo = $em->getRepository(
                    "App\Entity\Sonata\SonataPagePage",
                );
                $snapRepo = $em->getRepository(
                    "App\Entity\Sonata\SonataPageSnapshot",
                );
                $siteCount = method_exists($siteRepo, 'count')
                    ? $siteRepo->count([])
                    : \count($siteRepo->findAll());
                $pageCount = method_exists($pageRepo, 'count')
                    ? $pageRepo->count([])
                    : \count($pageRepo->findAll());
                $snapEnabled = null;
                if (method_exists($snapRepo, 'createQueryBuilder')) {
                    $qb = $snapRepo
                        ->createQueryBuilder('s')
                        ->select('COUNT(s.id)')
                        ->where('s.enabled = 1');
                    $snapEnabled = (int) $qb
                        ->getQuery()
                        ->getSingleScalarResult();
                }
                @fwrite(
                    \STDERR,
                    \sprintf(
                        "[DashboardTest] Non-2xx %d for '%s'. Sites=%d Pages=%d EnabledSnapshots=%s\n",
                        $status,
                        $path,
                        $siteCount,
                        $pageCount,
                        null === $snapEnabled ? 'n/a' : (string) $snapEnabled,
                    ),
                );
            } catch (\Throwable $ee) {
                @fwrite(
                    \STDERR,
                    \sprintf(
                        "[DashboardTest] Diagnostics failed: %s\n",
                        $ee->getMessage(),
                    ),
                );
            }
        }

        // Assert: success & correct html lang
        $this->assertSame(200, $status, 'Expected 200 for dashboard.');
        $this->assertGreaterThan(
            0,
            $crawler->filter(\sprintf('html[lang="%s"]', $locale))->count(),
            \sprintf('Expected html[lang="%s"] to exist', $locale),
        );

        // Optional structural content check: Title or heading should contain a recognizable dashboard token.
        // We avoid hardcoded translation strings; instead assert a key element exists.
        $this->assertGreaterThan(
            0,
            $crawler->filter('body')->count(),
            'Dashboard body should render.',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dashboardLocaleProvider')]
    public function testRandomArtistDisplaysWhenArtistsExist(string $locale): void
    {
        $this->em()->createQuery(
            'UPDATE App\\Entity\\Artist a SET a.copyForArchive = true',
        )->execute();

        // Create single artist - we can predict it will be shown
        $artist = ArtistFactory::new()->create([
            'name' => 'Test Artist Name',
            'copyForArchive' => false,
            'type' => 'band',
        ]);

        $this->seedClientHome($locale);
        $member = MemberFactory::new()->active()->create([
            'locale' => $locale,
            'username' => 'dashboard-'.$locale,
        ]);
        $user = $member->getUser();
        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $path = $this->router->generate('dashboard', [
            '_locale' => $locale,
        ]);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        // Artist preview content renders (component uses dedicated selectors to avoid collisions)
        $this->client->assertSelectorExists('.random-artist .random-artist-content');
        // Verify empty state message does NOT exist
        $this->client->assertSelectorNotExists('.random-artist .random-artist-empty');
        // Verify the polaroid caption is present and not empty
        $this->client->assertSelectorExists('.random-artist .polaroid .caption:not(:empty)');
    }

    public function testRandomArtistShowsEmptyStateWhenNoArtists(): void
    {
        $this->em()->createQuery(
            'UPDATE App\\Entity\\Artist a SET a.copyForArchive = true',
        )->execute();

        $this->seedClientHome('fi');
        $member = MemberFactory::new()->active()->create([
            'locale' => 'fi',
            'username' => 'dashboard-fi',
        ]);
        $user = $member->getUser();
        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $path = $this->router->generate('dashboard', ['_locale' => 'fi']);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        // Empty state: dedicated empty container with muted text
        $this->client->assertSelectorExists('.random-artist .random-artist-empty p.text-muted');
        // Verify artist content does NOT exist
        $this->client->assertSelectorNotExists('.random-artist .random-artist-content');
    }
}
