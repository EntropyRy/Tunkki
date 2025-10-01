<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates or normalizes root homepage pages for each Site so that
 * Finnish default site uses "/" and English site also has "/" (relative to its site path).
 *
 * Decisions / expectations:
 *  - routeName: page_slug
 *  - slug: '' (empty) while url = '/'
 *  - template: frontpage
 *  - type: App\PageService\FrontPage
 *  - name/title localized (Etusivu vs Home)
 *  - requestMethod includes GET|POST|HEAD|DELETE|PUT (mirrors legacy dev data)
 *  - No snapshot creation here; snapshots handled separately in CI
 *
 * This version purposely removes method_exists guards and assumes the Sonata Page
 * entity (BasePage derivative) provides all used setters / getters.
 */
final class PageFixtures extends Fixture implements DependentFixtureInterface
{
    private const string ROUTE_NAME = 'page_slug';
    private const string TYPE = 'App\\PageService\\FrontPage';
    private const string TEMPLATE = 'frontpage';
    private const string REQUEST_METHOD = 'GET|POST|HEAD|DELETE|PUT';

    public function getDependencies(): array
    {
        return [SiteFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var SonataPageSite[] $sites */
        $sites = $manager->getRepository(SonataPageSite::class)->findAll();

        foreach ($sites as $site) {
            $root = $manager->getRepository(SonataPagePage::class)->findOneBy([
                'site' => $site->getId(),
                'url' => '/',
            ]);

            if ($root instanceof SonataPagePage) {
                $this->normalizeExisting($root, $manager, $site->getLocale());
                continue;
            }

            $page = new SonataPagePage();
            $page->setSite($site);
            $page->setName('en' === $site->getLocale() ? 'Home' : 'Etusivu');
            $page->setTitle($page->getName());
            $page->setSlug('');
            $page->setUrl('/');
            $page->setRouteName(self::ROUTE_NAME);
            $page->setEnabled(true);
            $page->setDecorate(true);
            $page->setTemplateCode(self::TEMPLATE);
            $page->setType(self::TYPE);
            $page->setRequestMethod(self::REQUEST_METHOD);
            // Publication dates intentionally omitted (setPublicationDate* not available on this Page entity)

            $manager->persist($page);
        }

        $manager->flush();
    }

    private function normalizeExisting(SonataPagePage $page, ObjectManager $manager, string $locale): void
    {
        $changed = false;

        // Localize name/title
        $expectedName = 'en' === $locale ? 'Home' : 'Etusivu';
        if ($page->getName() !== $expectedName) {
            $page->setName($expectedName);
            $page->setTitle($expectedName);
            $changed = true;
        }

        if (self::ROUTE_NAME !== $page->getRouteName()) {
            $page->setRouteName(self::ROUTE_NAME);
            $changed = true;
        }
        if (self::TEMPLATE !== $page->getTemplateCode()) {
            $page->setTemplateCode(self::TEMPLATE);
            $changed = true;
        }
        if (self::TYPE !== $page->getType()) {
            $page->setType(self::TYPE);
            $changed = true;
        }
        if ('' !== $page->getSlug()) {
            $page->setSlug('');
            $changed = true;
        }
        if ('/' !== $page->getUrl()) {
            $page->setUrl('/');
            $changed = true;
        }
        if (self::REQUEST_METHOD !== $page->getRequestMethod()) {
            $page->setRequestMethod(self::REQUEST_METHOD);
            $changed = true;
        }
        // Skipping publication date normalization (setPublicationDate* methods not available)

        if ($changed) {
            $manager->persist($page);
        }
    }
}
