<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Factory\PageFactory;
use App\Factory\SiteFactory;
use App\PageService\FrontPage;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\Story;

/**
 * CmsBaselineStory.
 *
 * Seeds a single immutable, idempotent CMS baseline for Sonata PageBundle:
 *  - Exactly two Sites: fi (default, relativePath='') and en (relativePath='/en')
 *  - Per-site pages:
 *      - Root "/" (frontpage)
 *      - Events (/tapahtumat or /events)                type entropy.page.eventspage
 *      - Join   (/liity or /join-us)                    type sonata.page.service.default
 *      - Announcements (/tiedotukset or /announcements) type entropy.page.announcementspage, template "annnouncements"
 *      - Stream (/stream)                                type entropy.page.stream
 *
 * Intent:
 *  - Run once per test process (loaded in tests/bootstrap.php)
 *  - Idempotent: safe to load multiple times (normalizes & deduplicates instead of duplicating)
 *  - Mirrors the effective normalization performed by FixturesWebTestCase::ensureCmsBaseline()
 *    without per-test overhead (no route page generation or snapshots here).
 */
final class CmsBaselineStory extends Story
{
    public function build(): void
    {
        // 1) Ensure the canonical Sites: fi (default) and en (prefixed)
        $fi = $this->ensureSite('fi', true, '');
        $en = $this->ensureSite('en', false, '/en');

        // 2) Prune any sites that are not fi/en (remove their pages first to avoid FK issues)
        $this->pruneNonCanonicalLocales($fi, $en);

        // 3) Ensure & normalize root pages for both sites
        $fiRoot = $this->ensureRootPage($fi);
        $enRoot = $this->ensureRootPage($en);

        // 4) Ensure core pages per locale (events, join, announcements, stream)
        $this->ensureCorePagesForFi($fi, $fiRoot);
        $this->ensureCorePagesForEn($en, $enRoot);

        // 5) Deduplicate pages by URL within each site (keep first)
        $this->deduplicatePagesByUrl($fi);
        $this->deduplicatePagesByUrl($en);

        // 6) Expose references for tests (removed to avoid StoryManager reference requirement during CLI/bootstrap seeding)
    }

    // ---------------------------------------------------------------------
    // Sites
    // ---------------------------------------------------------------------

    private function ensureSite(string $locale, bool $isDefault, string $relativePath): SonataPageSite
    {
        /** @var RepositoryProxy $repo */
        $repo = SiteFactory::repository();

        /** @var SonataPageSite|null $existing */
        $existing = $repo->findOneBy(['locale' => $locale]);

        if (!$existing instanceof SonataPageSite) {
            // Create canonical site for this locale
            $site = SiteFactory::new([
                'name' => strtoupper($locale).' Site',
                'locale' => $locale,
                'host' => 'localhost',
                'isDefault' => $isDefault,
                'enabled' => true,
                'relativePath' => $relativePath,
                'enabledFrom' => new \DateTimeImmutable('-1 day'),
                'enabledTo' => null,
            ])->create();

            // Remove other sites of the same locale
            $this->removeOtherSitesOfLocale($locale, $site);

            return $site;
        }

        // Normalize attributes on the canonical site
        if (method_exists($existing, 'setHost')) {
            $existing->setHost('localhost');
        }
        if (method_exists($existing, 'setEnabled')) {
            $existing->setEnabled(true);
        }
        if (method_exists($existing, 'setIsDefault')) {
            $existing->setIsDefault($isDefault);
        }
        if (method_exists($existing, 'setRelativePath')) {
            $existing->setRelativePath($relativePath);
        }
        if (method_exists($existing, 'setEnabledFrom')) {
            $existing->setEnabledFrom(new \DateTimeImmutable('-1 day'));
        }
        if (method_exists($existing, 'setEnabledTo')) {
            $existing->setEnabledTo(null);
        }

        // Remove duplicates for this locale (keep $existing)
        $this->removeOtherSitesOfLocale($locale, $existing);

        return $existing;
    }

    private function removeOtherSitesOfLocale(string $locale, SonataPageSite $canonical): void
    {
        /** @var RepositoryProxy $siteRepo */
        $siteRepo = SiteFactory::repository();
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        /** @var SonataPageSite[] $all */
        $all = $siteRepo->findBy(['locale' => $locale]) ?? [];
        foreach ($all as $site) {
            if ($site === $canonical) {
                continue;
            }
            // Remove pages first to avoid FK constraint violations
            $pages = $pageRepo->findBy(['site' => $site]) ?? [];
            foreach ($pages as $pg) {
                if (method_exists($pageRepo, 'remove')) {
                    $pageRepo->remove($pg, false);
                }
            }
            if (method_exists($siteRepo, 'remove')) {
                $siteRepo->remove($site, false);
            }
        }
        if (method_exists($siteRepo, 'flush')) {
            $siteRepo->flush();
        }
    }

    private function pruneNonCanonicalLocales(SonataPageSite $fi, SonataPageSite $en): void
    {
        /** @var RepositoryProxy $siteRepo */
        $siteRepo = SiteFactory::repository();
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        /** @var SonataPageSite[] $all */
        $all = $siteRepo->findAll() ?? [];

        foreach ($all as $site) {
            if ($site === $fi || $site === $en) {
                continue;
            }
            $loc = method_exists($site, 'getLocale') ? (string) $site->getLocale() : null;
            if (!\in_array($loc, ['fi', 'en'], true)) {
                $pages = $pageRepo->findBy(['site' => $site]) ?? [];
                foreach ($pages as $pg) {
                    if (method_exists($pageRepo, 'remove')) {
                        $pageRepo->remove($pg, false);
                    }
                }
                if (method_exists($siteRepo, 'remove')) {
                    $siteRepo->remove($site, false);
                }
            }
        }
        if (method_exists($siteRepo, 'flush')) {
            $siteRepo->flush();
        }
    }

    // ---------------------------------------------------------------------
    // Root page
    // ---------------------------------------------------------------------

    private function ensureRootPage(SonataPageSite $site): SonataPagePage
    {
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        /** @var SonataPagePage|null $root */
        $root = $pageRepo->findOneBy(['site' => $site, 'url' => '/']);

        if (!$root instanceof SonataPagePage) {
            // Create homepage with canonical attributes
            return PageFactory::new()
                ->homepage()
                ->withSite($site)
                ->create();
        }

        // Normalize existing root
        if (method_exists($root, 'getUrl') && method_exists($root, 'setUrl') && '/' !== (string) $root->getUrl()) {
            $root->setUrl('/');
        }
        if (method_exists($root, 'getRouteName') && method_exists($root, 'setRouteName') && 'page_slug' !== (string) $root->getRouteName()) {
            $root->setRouteName('page_slug');
        }
        if (method_exists($root, 'getTemplateCode') && method_exists($root, 'setTemplateCode') && 'frontpage' !== (string) $root->getTemplateCode()) {
            $root->setTemplateCode('frontpage');
        }
        if (method_exists($root, 'getType') && method_exists($root, 'setType') && FrontPage::class !== (string) $root->getType()) {
            $root->setType(FrontPage::class);
        }
        if (method_exists($root, 'isEnabled') && method_exists($root, 'setEnabled') && !$root->isEnabled()) {
            $root->setEnabled(true);
        }
        if (method_exists($root, 'getDecorate') && method_exists($root, 'setDecorate') && !$root->getDecorate()) {
            $root->setDecorate(true);
        }
        if (method_exists($root, 'getRequestMethod') && method_exists($root, 'setRequestMethod') && 'GET|POST|HEAD|DELETE|PUT' !== (string) $root->getRequestMethod()) {
            $root->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
        }
        if (method_exists($pageRepo, 'flush')) {
            $pageRepo->flush();
        }

        return $root;
    }

    // ---------------------------------------------------------------------
    // Core pages per locale
    // ---------------------------------------------------------------------

    private function ensureCorePagesForFi(SonataPageSite $fi, SonataPagePage $root): void
    {
        // Events (/tapahtumat)
        $this->ensurePage(
            $fi,
            $root,
            url: '/tapahtumat',
            slug: 'tapahtumat',
            name: 'Tapahtumat',
            title: 'Tapahtumat',
            routeName: 'page_slug',
            templateCode: 'default',
            type: 'entropy.page.eventspage',
            requestMethod: 'GET|POST|HEAD|DELETE|PUT',
            alias: '_page_alias_events_fi',
            position: 1
        );

        // Join (/liity)
        $this->ensurePage(
            $fi,
            $root,
            url: '/liity',
            slug: 'liity',
            name: 'Liity',
            title: 'Liity Jäseneksi',
            routeName: 'page_slug',
            templateCode: 'onecolumn',
            type: 'sonata.page.service.default',
            requestMethod: 'GET|POST|HEAD|DELETE|PUT',
            alias: '_page_alias_join_us_fi',
            position: 1,
            metaDescriptionIfMissing: 'Liity Jäseneksi'
        );

        // Announcements (/tiedotukset) — templateCode intentionally "annnouncements"
        $this->ensureAnnouncements(
            $fi,
            $root,
            url: '/tiedotukset',
            slug: 'tiedotukset',
            name: 'Tiedotukset',
            title: 'Tiedotukset',
            alias: '_page_alias_announcements_fi',
            position: 3
        );

        // Stream (/stream)
        $this->ensureStream($fi, $root);
    }

    private function ensureCorePagesForEn(SonataPageSite $en, SonataPagePage $root): void
    {
        // Events (/events)
        $this->ensurePage(
            $en,
            $root,
            url: '/events',
            slug: 'events',
            name: 'Events',
            title: 'Events',
            routeName: 'page_slug',
            templateCode: 'default',
            type: 'entropy.page.eventspage',
            requestMethod: 'GET|POST|HEAD|DELETE|PUT',
            alias: '_page_alias_events_en',
            position: 1
        );

        // Join (/join-us)
        $this->ensurePage(
            $en,
            $root,
            url: '/join-us',
            slug: 'join-us',
            name: 'Join Us',
            title: 'Join Us',
            routeName: 'page_slug',
            templateCode: 'onecolumn',
            type: 'sonata.page.service.default',
            requestMethod: 'GET|POST|HEAD|DELETE|PUT',
            alias: '_page_alias_join_us_en',
            position: 1,
            metaDescriptionIfMissing: 'Join Us'
        );

        // Announcements (/announcements) — templateCode intentionally "annnouncements"
        $this->ensureAnnouncements(
            $en,
            $root,
            url: '/announcements',
            slug: 'announcements',
            name: 'Announcements',
            title: 'Announcements',
            alias: '_page_alias_announcements_en',
            position: 3
        );

        // Stream (/stream)
        $this->ensureStream($en, $root);
    }

    private function ensurePage(
        SonataPageSite $site,
        SonataPagePage $root,
        string $url,
        string $slug,
        string $name,
        string $title,
        string $routeName,
        string $templateCode,
        string $type,
        string $requestMethod,
        string $alias,
        int $position,
        ?string $metaDescriptionIfMissing = null,
    ): SonataPagePage {
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        // Find existing by alias OR slug OR url (prefer alias)
        $page =
            $pageRepo->findOneBy(['site' => $site, 'pageAlias' => $alias]) ??
            ($pageRepo->findOneBy(['site' => $site, 'slug' => $slug]) ??
                $pageRepo->findOneBy(['site' => $site, 'url' => $url]));

        if (!$page instanceof SonataPagePage) {
            // Create new page
            return PageFactory::new()
                ->withSite($site)
                ->with([
                    'routeName' => $routeName,
                    'name' => $name,
                    'title' => $title,
                    'slug' => $slug,
                    'url' => $url,
                    'enabled' => true,
                    'decorate' => true,
                    'type' => $type,
                    'templateCode' => $templateCode,
                    'requestMethod' => $requestMethod,
                    'pageAlias' => $alias,
                    'parent' => $root,
                    'position' => $position,
                ])
                ->create();
        }

        // Normalize existing page
        if (method_exists($page, 'setParent')) {
            $page->setParent($root);
        }
        if (method_exists($page, 'setPosition')) {
            $page->setPosition($position);
        }
        if (method_exists($page, 'setRouteName')) {
            $page->setRouteName($routeName);
        }
        if (method_exists($page, 'setName')) {
            $page->setName($name);
        }
        if (method_exists($page, 'setTitle')) {
            $page->setTitle($title);
        }
        if (method_exists($page, 'setSlug')) {
            $page->setSlug($slug);
        }
        if (method_exists($page, 'setUrl')) {
            $page->setUrl($url);
        }
        if (method_exists($page, 'setEnabled')) {
            $page->setEnabled(true);
        }
        if (method_exists($page, 'setDecorate')) {
            $page->setDecorate(true);
        }
        if (method_exists($page, 'setType')) {
            $page->setType($type);
        }
        if (method_exists($page, 'setTemplateCode')) {
            $page->setTemplateCode($templateCode);
        }
        if (method_exists($page, 'setRequestMethod')) {
            $page->setRequestMethod($requestMethod);
        }
        if (method_exists($page, 'setPageAlias')) {
            $page->setPageAlias($alias);
        }
        if (null !== $metaDescriptionIfMissing && method_exists($page, 'getMetaDescription') && method_exists($page, 'setMetaDescription') && null === $page->getMetaDescription()) {
            $page->setMetaDescription($metaDescriptionIfMissing);
        }
        if (method_exists($pageRepo, 'flush')) {
            $pageRepo->flush();
        }

        return $page;
    }

    private function ensureAnnouncements(
        SonataPageSite $site,
        SonataPagePage $root,
        string $url,
        string $slug,
        string $name,
        string $title,
        string $alias,
        int $position,
    ): void {
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        $candidates = $pageRepo->findBy(['site' => $site, 'url' => $url]) ?? [];

        // Prefer a page already configured with the correct template/type
        $chosen = null;
        foreach ($candidates as $candidate) {
            if (
                method_exists($candidate, 'getTemplateCode')
                && method_exists($candidate, 'getType')
                && 'annnouncements' === (string) $candidate->getTemplateCode()
                && 'entropy.page.announcementspage' === (string) $candidate->getType()
            ) {
                $chosen = $candidate;
                break;
            }
        }
        if (null === $chosen) {
            $chosen = $candidates[0] ?? null;
        }

        // Remove duplicates beyond the chosen one
        foreach ($candidates as $dup) {
            if ($dup !== $chosen && method_exists($pageRepo, 'remove')) {
                $pageRepo->remove($dup, false);
            }
        }

        if (!$chosen instanceof SonataPagePage) {
            // Create fresh announcements page
            PageFactory::new()
                ->withSite($site)
                ->with([
                    'routeName' => 'page_slug',
                    'name' => $name,
                    'title' => $title,
                    'slug' => $slug,
                    'url' => $url,
                    'enabled' => true,
                    'decorate' => true,
                    'type' => 'entropy.page.announcementspage',
                    'templateCode' => 'annnouncements',
                    'requestMethod' => 'GET|POST|HEAD|DELETE|PUT',
                    'pageAlias' => $alias,
                    'parent' => $root,
                    'position' => $position,
                ])
                ->create();
        } else {
            // Normalize attributes
            if (method_exists($chosen, 'setParent')) {
                $chosen->setParent($root);
            }
            if (method_exists($chosen, 'setPosition')) {
                $chosen->setPosition($position);
            }
            if (method_exists($chosen, 'setRouteName')) {
                $chosen->setRouteName('page_slug');
            }
            if (method_exists($chosen, 'setName')) {
                $chosen->setName($name);
            }
            if (method_exists($chosen, 'setTitle')) {
                $chosen->setTitle($title);
            }
            if (method_exists($chosen, 'setSlug')) {
                $chosen->setSlug($slug);
            }
            if (method_exists($chosen, 'setUrl')) {
                $chosen->setUrl($url);
            }
            if (method_exists($chosen, 'setEnabled')) {
                $chosen->setEnabled(true);
            }
            if (method_exists($chosen, 'setDecorate')) {
                $chosen->setDecorate(true);
            }
            if (method_exists($chosen, 'setType')) {
                $chosen->setType('entropy.page.announcementspage');
            }
            if (method_exists($chosen, 'setTemplateCode')) {
                $chosen->setTemplateCode('annnouncements');
            }
            if (method_exists($chosen, 'setRequestMethod')) {
                $chosen->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
            }
            if (method_exists($chosen, 'setPageAlias')) {
                $chosen->setPageAlias($alias);
            }

            if (method_exists($pageRepo, 'flush')) {
                $pageRepo->flush();
            }
        }
    }

    private function ensureStream(SonataPageSite $site, SonataPagePage $root): void
    {
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        $streams = $pageRepo->findBy(['site' => $site, 'url' => '/stream']) ?? [];
        $chosen = null;
        foreach ($streams as $candidate) {
            if (
                method_exists($candidate, 'getTemplateCode')
                && method_exists($candidate, 'getType')
                && 'stream' === (string) $candidate->getTemplateCode()
                && 'entropy.page.stream' === (string) $candidate->getType()
            ) {
                $chosen = $candidate;
                break;
            }
        }
        if (null === $chosen) {
            $chosen = $streams[0] ?? null;
        }

        // Remove duplicates beyond the canonical one
        foreach ($streams as $dup) {
            if ($dup !== $chosen && method_exists($pageRepo, 'remove')) {
                $pageRepo->remove($dup, false);
            }
        }

        if (!$chosen instanceof SonataPagePage) {
            PageFactory::new()
                ->withSite($site)
                ->with([
                    'routeName' => 'page_slug',
                    'name' => 'Stream',
                    'title' => 'Stream',
                    'slug' => 'stream',
                    'url' => '/stream',
                    'enabled' => true,
                    'decorate' => true,
                    'type' => 'entropy.page.stream',
                    'templateCode' => 'stream',
                    'requestMethod' => 'GET|POST|HEAD',
                    'parent' => $root,
                    'position' => 2,
                ])
                ->create();
        } else {
            if (method_exists($chosen, 'setParent')) {
                $chosen->setParent($root);
            }
            if (method_exists($chosen, 'setPosition')) {
                $chosen->setPosition(2);
            }
            if (method_exists($chosen, 'setRouteName')) {
                $chosen->setRouteName('page_slug');
            }
            if (method_exists($chosen, 'setName')) {
                $chosen->setName('Stream');
            }
            if (method_exists($chosen, 'setTitle')) {
                $chosen->setTitle('Stream');
            }
            if (method_exists($chosen, 'setSlug')) {
                $chosen->setSlug('stream');
            }
            if (method_exists($chosen, 'setUrl')) {
                $chosen->setUrl('/stream');
            }
            if (method_exists($chosen, 'setEnabled')) {
                $chosen->setEnabled(true);
            }
            if (method_exists($chosen, 'setDecorate')) {
                $chosen->setDecorate(true);
            }
            if (method_exists($chosen, 'setType')) {
                $chosen->setType('entropy.page.stream');
            }
            if (method_exists($chosen, 'setTemplateCode')) {
                $chosen->setTemplateCode('stream');
            }
            if (method_exists($chosen, 'setRequestMethod')) {
                $chosen->setRequestMethod('GET|POST|HEAD');
            }

            if (method_exists($pageRepo, 'flush')) {
                $pageRepo->flush();
            }
        }
    }

    // ---------------------------------------------------------------------
    // Deduplication
    // ---------------------------------------------------------------------

    private function deduplicatePagesByUrl(SonataPageSite $site): void
    {
        /** @var RepositoryProxy $pageRepo */
        $pageRepo = PageFactory::repository();

        /** @var SonataPagePage[] $pages */
        $pages = $pageRepo->findBy(['site' => $site]) ?? [];

        $seen = [];
        foreach ($pages as $pg) {
            $url = method_exists($pg, 'getUrl') ? (string) $pg->getUrl() : null;
            if (null === $url) {
                continue;
            }
            if (!isset($seen[$url])) {
                $seen[$url] = $pg;
                continue;
            }
            // Remove duplicates beyond the first
            if (method_exists($pageRepo, 'remove')) {
                $pageRepo->remove($pg, false);
            }
        }
        if (method_exists($pageRepo, 'flush')) {
            $pageRepo->flush();
        }
    }
}
