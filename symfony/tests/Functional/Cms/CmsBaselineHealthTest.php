<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cms;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * CmsBaselineHealthTest.
 *
 * Verifies that the minimal CMS baseline (FI + EN Sites with root pages) is
 * present and idempotently created via FixturesWebTestCase::ensureCmsBaseline().
 *
 * Guarantees:
 *  - Both / and /en/ resolve with HTTP 200.
 *  - At least 2 Site rows exist (Finnish default + English).
 *  - Repeated homepage requests do not create duplicate Sites/Pages (row counts stable).
 *
 * Rationale:
 *  Randomized test order (e.g. Infection --order-by=random) must not depend on an
 *  earlier test having seeded Sonata Page entities. Seeding is now on-demand.
 */
final class CmsBaselineHealthTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Initialize the site-aware client (triggers ensureCmsBaseline()).
        $this->initSiteAwareClient();
    }

    /**
     * Data provider: locales with expected homepage path prefix semantics.
     *
     * @return array<int,array{locale:string}>
     */
    public static function localeProvider(): array
    {
        return [
            ['locale' => 'fi'],
            ['locale' => 'en'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function testHomepageAccessible(string $locale): void
    {
        $client = $this->client();
        $path = 'fi' === $locale ? '/' : '/en/';

        $client->request('GET', $path);
        self::assertResponseIsSuccessful(sprintf('Homepage request failed for locale=%s path=%s', $locale, $path));
        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Expected 200 HTTP status for homepage'
        );

        // Defensive: confirm exactly two sites (FI + EN) and required pages exist
        $siteRepo = $this->em()->getRepository(SonataPageSite::class);
        $siteCount = method_exists($siteRepo, 'count') ? $siteRepo->count([]) : count($siteRepo->findAll());
        self::assertSame(2, $siteCount, 'Expected exactly two CMS sites (FI default + EN).');

        $sites = method_exists($siteRepo, 'findAll') ? $siteRepo->findAll() : [];
        $locales = array_map(fn ($s) => method_exists($s, 'getLocale') ? (string) $s->getLocale() : '', $sites);
        sort($locales);
        self::assertSame(['en', 'fi'], $locales, 'Expected only FI and EN sites.');

        $pageRepo = $this->em()->getRepository(SonataPagePage::class);
        foreach ($sites as $site) {
            $root = method_exists($pageRepo, 'findOneBy') ? $pageRepo->findOneBy(['site' => $site, 'url' => '/']) : null;
            self::assertNotNull(
                $root,
                sprintf('Root page missing for site locale=%s', method_exists($site, 'getLocale') ? $site->getLocale() : 'n/a')
            );

            $locale = method_exists($site, 'getLocale') ? (string) $site->getLocale() : 'fi';
            $eventsUrl = 'en' === $locale ? '/events' : '/tapahtumat';
            $joinUrl = 'en' === $locale ? '/join-us' : '/liity';

            $events = method_exists($pageRepo, 'findOneBy') ? $pageRepo->findOneBy(['site' => $site, 'url' => $eventsUrl]) : null;
            self::assertNotNull($events, sprintf('Events page (%s) missing for site locale=%s', $eventsUrl, $locale));

            $join = method_exists($pageRepo, 'findOneBy') ? $pageRepo->findOneBy(['site' => $site, 'url' => $joinUrl]) : null;
            self::assertNotNull($join, sprintf('Join Us page (%s) missing for site locale=%s', $joinUrl, $locale));
        }
    }

    public function testCmsBaselineIdempotent(): void
    {
        $em = $this->em();
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        $initialSites = method_exists($siteRepo, 'count') ? $siteRepo->count([]) : count($siteRepo->findAll());
        $initialPages = method_exists($pageRepo, 'count') ? $pageRepo->count([]) : count($pageRepo->findAll());

        // Trigger additional baseline-related requests
        $this->client()->request('GET', '/');
        self::assertResponseIsSuccessful();
        $this->client()->request('GET', '/en/');
        self::assertResponseIsSuccessful();

        $afterSites = method_exists($siteRepo, 'count') ? $siteRepo->count([]) : count($siteRepo->findAll());
        $afterPages = method_exists($pageRepo, 'count') ? $pageRepo->count([]) : count($pageRepo->findAll());

        self::assertSame(2, $initialSites, 'Expected exactly two CMS sites initially.');
        self::assertSame(2, $afterSites, 'Expected exactly two CMS sites after homepage requests.');
        self::assertSame($initialSites, $afterSites, 'CMS baseline seeding should be idempotent (site count changed).');
        self::assertSame($initialPages, $afterPages, 'CMS baseline seeding should be idempotent (page count changed).');
    }
}
