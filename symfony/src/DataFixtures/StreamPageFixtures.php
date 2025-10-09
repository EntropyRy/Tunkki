<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates a Sonata Page entry for the Stream page so that the application
 * can serve /en/stream (and /stream under the EN site context) using the
 * App\PageService\StreamPage service and the stream.html.twig template.
 *
 * Rationale:
 *  - Existing fixtures provide Sites (SiteFixtures) and a front page (PageFixtures)
 *    but no dedicated Stream page.
 *  - The Stream Live Components (ArtistControl, Player, etc.) require a page
 *    that renders templates/stream.html.twig (template code "stream").
 *  - Tests can now request /en/stream after fixtures load to exercise the
 *    artist join/leave workflow around an online Stream entity.
 *
 * Conventions mirrored from PageFixtures:
 *  - routeName: page_slug
 *  - templateCode: stream
 *  - type: App\PageService\StreamPage
 *  - url: /stream (relative to site root; EN site has relativePath /en so final path is /en/stream)
 *  - slug: stream
 *  - requestMethod: GET|POST|HEAD
 *
 * Idempotency:
 *  - If a page with url '/stream' already exists for the EN site, this fixture
 *    does nothing (supports repeated loads in test pipelines).
 */
final class StreamPageFixtures extends Fixture implements DependentFixtureInterface
{
    private const string ROUTE_NAME = 'page_slug';
    private const string TEMPLATE_CODE = 'stream';
    private const string TYPE = 'entropy.page.stream';
    private const string REQUEST_METHOD = 'GET|POST|HEAD';
    private const string PAGE_URL = '/stream';
    private const string PAGE_SLUG = 'stream';
    private const string PAGE_NAME = 'Stream';

    public function getDependencies(): array
    {
        return [SiteFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Create /stream page for both EN and FI locales (idempotent)
        $this->createStreamPageForLocale($manager, 'en');
        $this->createStreamPageForLocale($manager, 'fi');
    }

    /**
     * Idempotently create or normalize the stream page for a given locale.
     *
     * For EN (relativePath /en) the stored page URL remains "/stream" so the
     * public path resolves to /en/stream; for FI (relativePath "/") it resolves
     * to /stream.
     */
    private function createStreamPageForLocale(
        ObjectManager $manager,
        string $locale,
    ): void {
        /** @var SonataPageSite|null $site */
        $site = $manager
            ->getRepository(SonataPageSite::class)
            ->findOneBy(['locale' => $locale]);

        if (!$site instanceof SonataPageSite) {
            return; // Site for locale not present; skip
        }

        $repo = $manager->getRepository(SonataPagePage::class);

        /** @var SonataPagePage|null $existing */
        $existing = $repo->findOneBy([
            'site' => $site,
            'url' => self::PAGE_URL,
        ]);

        if ($existing instanceof SonataPagePage) {
            $changed = false;
            if (self::TEMPLATE_CODE !== $existing->getTemplateCode()) {
                $existing->setTemplateCode(self::TEMPLATE_CODE);
                $changed = true;
            }
            if (self::TYPE !== $existing->getType()) {
                $existing->setType(self::TYPE);
                $changed = true;
            }
            if (self::ROUTE_NAME !== $existing->getRouteName()) {
                $existing->setRouteName(self::ROUTE_NAME);
                $changed = true;
            }
            if (self::PAGE_SLUG !== $existing->getSlug()) {
                $existing->setSlug(self::PAGE_SLUG);
                $changed = true;
            }
            if (self::PAGE_NAME !== $existing->getName()) {
                $existing->setName(self::PAGE_NAME);
                $existing->setTitle(self::PAGE_NAME);
                $changed = true;
            }
            if ($changed) {
                $manager->persist($existing);
                $manager->flush();
            }

            return;
        }

        $page = new SonataPagePage();
        $page->setSite($site);
        $page->setName(self::PAGE_NAME);
        $page->setTitle(self::PAGE_NAME);
        $page->setSlug(self::PAGE_SLUG);
        $page->setUrl(self::PAGE_URL);
        $page->setRouteName(self::ROUTE_NAME);
        $page->setEnabled(true);
        $page->setDecorate(true);
        $page->setTemplateCode(self::TEMPLATE_CODE);
        $page->setType(self::TYPE);
        $page->setRequestMethod(self::REQUEST_METHOD);

        $manager->persist($page);
        $manager->flush();
    }
}
