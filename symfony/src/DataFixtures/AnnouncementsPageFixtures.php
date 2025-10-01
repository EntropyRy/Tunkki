<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates / normalizes localized "Announcements" listing pages (FI + EN) under each site's root page.
 *
 * Expected technical aliases (for routing via path('_page_alias_announcements_<locale>')):
 *   _page_alias_announcements_fi
 *   _page_alias_announcements_en
 *
 * FI example legacy row (illustrative):
 *   site_id=1, parent=root, route_name=page_slug, page_alias=_page_alias_announcements_fi,
 *   type=entropy.page.announcementspage, name=Tiedotukset, slug=tiedotukset, url=/tiedotukset,
 *   templateCode=announcements (legacy data showed 'annnouncements' – assumed typo; we use canonical 'announcements')
 *
 * EN counterpart:
 *   page_alias=_page_alias_announcements_en, name=Announcements, slug=announcements, url=/announcements
 *
 * Design choices:
 *  - Idempotent: if page (by alias or slug+site) exists, it is normalized rather than duplicated.
 *  - Position: set to 3 (assuming 1=events, 2=join-us); harmless if siblings differ.
 *  - No snapshot creation here (handled by CI / deployment after fixtures load).
 *  - No method_exists guards per project conventions; assumes Sonata page entity provides used setters.
 *  - Publication date setters intentionally omitted (entity model does not expose them).
 */
final class AnnouncementsPageFixtures extends Fixture implements DependentFixtureInterface
{
    private const string ROUTE_NAME = 'page_slug';
    private const string TYPE = 'entropy.page.announcementspage';
    private const string TEMPLATE = 'annnouncements';
    private const string REQUEST_METHOD = 'GET|POST|HEAD|DELETE|PUT';

    public const string ALIAS_FI = '_page_alias_announcements_fi';
    public const string ALIAS_EN = '_page_alias_announcements_en';

    public const string REFERENCE_FI = 'page_announcements_fi';
    public const string REFERENCE_EN = 'page_announcements_en';

    public function getDependencies(): array
    {
        // Root pages must exist first.
        return [
            PageFixtures::class,
            // Optional: If ordering relative to Events / JoinUs is critical, add:
            // EventsPageFixtures::class,
            // JoinUsPageFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var SonataPageSite[] $sites */
        $sites = $manager->getRepository(SonataPageSite::class)->findAll();
        $pageRepo = $manager->getRepository(SonataPagePage::class);

        foreach ($sites as $site) {
            $locale = $site->getLocale();
            if (!in_array($locale, ['fi', 'en'], true)) {
                continue;
            }

            $alias = 'en' === $locale ? self::ALIAS_EN : self::ALIAS_FI;
            $slug = 'en' === $locale ? 'announcements' : 'tiedotukset';
            $name = 'en' === $locale ? 'Announcements' : 'Tiedotukset';
            $url = '/'.$slug;

            // Find the root (homepage) page (url '/')
            $root = $pageRepo->findOneBy([
                'site' => $site->getId(),
                'url' => '/',
            ]);

            if (!$root instanceof SonataPagePage) {
                // Earlier fixtures failed; skip gracefully.
                continue;
            }

            // Locate existing announcements page by alias first
            $page = $pageRepo->findOneBy([
                'site' => $site->getId(),
                'pageAlias' => $alias,
            ]);

            if (!$page instanceof SonataPagePage) {
                // Fallback attempts (slug or url) for previously created but un-aliased pages
                $page = $pageRepo->findOneBy([
                    'site' => $site->getId(),
                    'slug' => $slug,
                ]) ?? $pageRepo->findOneBy([
                    'site' => $site->getId(),
                    'url' => $url,
                ]);
            }

            $isNew = false;
            if (!$page instanceof SonataPagePage) {
                $page = new SonataPagePage();
                $page->setSite($site);
                $page->setParent($root);
                $page->setPosition(3);
                $isNew = true;
            }

            // Normalize page attributes
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

            $manager->persist($page);

            if ('en' === $locale) {
                $this->addReference(self::REFERENCE_EN, $page);
            } else {
                $this->addReference(self::REFERENCE_FI, $page);
            }

            if ($isNew) {
                // Placeholder for optional debug logging
            }
        }

        $manager->flush();
    }
}
