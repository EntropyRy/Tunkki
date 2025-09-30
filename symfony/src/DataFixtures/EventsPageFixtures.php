<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates localized "Events" pages (FI + EN) under each site's root homepage.
 *
 * Mirrors legacy dev database rows (example reference):
 *
 *  id | site_id | parent_id | route_name | page_alias               | type                       | position | enabled | decorate | edited | name        | slug        | url          | custom_url | request_method             | title       | template | ...
 *  18 |   1     |   8       | page_slug  | _page_alias_events_fi    | entropy.page.eventspage    | 1        | 1       | 1        | 0      | Tapahtumat  | tapahtumat  | /tapahtumat  |            | GET|POST|HEAD|DELETE|PUT   | Tapahtumat | events
 *  24 |   2     |   11      | page_slug  | _page_alias_events_en    | entropy.page.eventspage    | 1        | 1       | 1        | 0      | Events      | events      | /events      |            | GET|POST|HEAD|DELETE|PUT   | Events     | events
 *
 * Notes:
 *  - Parent is the root page for the site (page having url="/").
 *  - We resolve root dynamically (no hard-coded IDs).
 *  - Ids will differ from legacy DB; functional behavior is what matters.
 *  - If a target page already exists (matched by alias OR slug+site), we normalize it.
 */
final class EventsPageFixtures extends Fixture implements DependentFixtureInterface
{
    public const string ROUTE_NAME       = 'page_slug';
    public const string TYPE             = 'entropy.page.eventspage';
    public const string TEMPLATE         = 'events';
    public const string REQUEST_METHOD   = 'GET|POST|HEAD|DELETE|PUT';

    public const string ALIAS_FI         = '_page_alias_events_fi';
    public const string ALIAS_EN         = '_page_alias_events_en';

    public const string REFERENCE_FI     = 'page_events_fi';
    public const string REFERENCE_EN     = 'page_events_en';

    public function getDependencies(): array
    {
        // Ensure root/home pages exist first
        return [
            PageFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var SonataPageSite[] $sites */
        $sites = $manager->getRepository(SonataPageSite::class)->findAll();
        $pageRepo = $manager->getRepository(SonataPagePage::class);

        foreach ($sites as $site) {
            $locale = $site->getLocale();
            if (!\in_array($locale, ['fi', 'en'], true)) {
                continue; // Only create for the two canonical locales
            }

            $alias = $locale === 'en' ? self::ALIAS_EN : self::ALIAS_FI;
            $slug  = $locale === 'en' ? 'events' : 'tapahtumat';
            $name  = $locale === 'en' ? 'Events' : 'Tapahtumat';
            $url   = '/' . $slug; // Matches legacy example (site relative path handled by Sonata Site)

            // Locate the root page for this site (url '/')
            $root = $pageRepo->findOneBy([
                'site' => $site->getId(),
                'url'  => '/',
            ]);

            if (!$root instanceof SonataPagePage) {
                // If root is missing something is fundamentally wrong with earlier fixtures
                continue;
            }

            // Try to find existing events page by alias first
            $page = $pageRepo->findOneBy([
                'site'      => $site->getId(),
                'pageAlias' => $alias,
            ]);

            if (!$page instanceof SonataPagePage) {
                // Fallback: match by slug (or url) if alias missing
                $page = $pageRepo->findOneBy([
                    'site' => $site->getId(),
                    'slug' => $slug,
                ]) ?? $pageRepo->findOneBy([
                    'site' => $site->getId(),
                    'url'  => $url,
                ]);
            }

            $isNew = false;
            if (!$page instanceof SonataPagePage) {
                $page = new SonataPagePage();
                $page->setSite($site);
                $page->setParent($root);
                $page->setPosition(1);
                $isNew = true;
            }

            // Normalize / set fields (assume all setters exist on the Sonata page entity)
            $page->setRouteName(self::ROUTE_NAME);
            $page->setName($name);
            $page->setTitle($name);
            $page->setSlug($slug);
            $page->setUrl($url);
            $page->setEnabled(true);
            $page->setDecorate(true);
            $page->setType(self::TYPE);
            $page->setRequestMethod(self::REQUEST_METHOD);
            $page->setTemplateCode(self::TEMPLATE);
            $page->setPageAlias($alias);

            // Publication window omitted: publication date setters not available on this Page entity

            $manager->persist($page);

            if ($locale === 'en') {
                $this->addReference(self::REFERENCE_EN, $page);
            } else {
                $this->addReference(self::REFERENCE_FI, $page);
            }

            // Optionally mark as new (for debug logging in future if needed)
            if ($isNew) {
                // Placeholder for potential logging or debug hooks
            }
        }

        $manager->flush();
    }
}
