<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Test;
use Sonata\PageBundle\Model\PageInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Zenstruck\Foundry\Persistence\Proxy;

final class SitemapControllerTest extends FixturesWebTestCase
{
    #[Test]
    public function testSitemapXmlContainsMenuPagesUpToThreeLevelsAndSkipsInvalidExternalEvent(): void
    {
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');

        $siteRepo = $this->em()->getRepository(SonataPageSite::class);
        $siteFi = $siteRepo->findOneBy(['locale' => 'fi']);
        $siteEn = $siteRepo->findOneBy(['locale' => 'en']);
        self::assertInstanceOf(SonataPageSite::class, $siteFi);
        self::assertInstanceOf(SonataPageSite::class, $siteEn);

        // Avoid creating new pages (page__page is a high-contention table under ParaTest).
        // Reuse CMS baseline pages seeded by CmsBaselineStory.
        $pageRepo = $this->em()->getRepository(SonataPagePage::class);
        $pageRootFi = $pageRepo->findOneBy(['site' => $siteFi, 'pageAlias' => '_page_alias_events_fi']);
        $pageRootEn = $pageRepo->findOneBy(['site' => $siteEn, 'pageAlias' => '_page_alias_events_en']);
        self::assertInstanceOf(SonataPagePage::class, $pageRootFi);
        self::assertInstanceOf(SonataPagePage::class, $pageRootEn);

        $pageLv2Fi = $pageRepo->findOneBy(['site' => $siteFi, 'pageAlias' => '_page_alias_join_us_fi']);
        $pageLv2En = $pageRepo->findOneBy(['site' => $siteEn, 'pageAlias' => '_page_alias_join_us_en']);
        self::assertInstanceOf(SonataPagePage::class, $pageLv2Fi);
        self::assertInstanceOf(SonataPagePage::class, $pageLv2En);

        $pageLv3Fi = $pageRepo->findOneBy(['site' => $siteFi, 'url' => '/stream']);
        $pageLv3En = $pageRepo->findOneBy(['site' => $siteEn, 'url' => '/stream']);
        self::assertInstanceOf(SonataPagePage::class, $pageLv3Fi);
        self::assertInstanceOf(SonataPagePage::class, $pageLv3En);

        $suffix = uniqid('sitemap_', true);
        $root = (new Menu())
            ->setLabel('Sitemap Root '.$suffix)
            ->setNimi('Sitemap Root '.$suffix)
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->asPage($pageRootFi))
            ->setPageEn($this->asPage($pageRootEn));

        $lvl2 = (new Menu())
            ->setLabel('Sitemap Level 2 '.$suffix)
            ->setNimi('Sitemap Level 2 '.$suffix)
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->asPage($pageLv2Fi))
            ->setPageEn($this->asPage($pageLv2En));
        $root->addChild($lvl2);

        $lvl3 = (new Menu())
            ->setLabel('Sitemap Level 3 '.$suffix)
            ->setNimi('Sitemap Level 3 '.$suffix)
            ->setEnabled(true)
            ->setPosition(1)
            ->setPageFi($this->asPage($pageLv3Fi))
            ->setPageEn($this->asPage($pageLv3En));
        $lvl2->addChild($lvl3);

        $this->em()->persist($root);
        $this->em()->persist($lvl2);
        $this->em()->persist($lvl3);
        $this->em()->flush();
        $this->em()->clear();

        $event = EventFactory::new()->published()->create([
            'url' => 'sitemap-event-'.uniqid('', true),
        ]);

        // External event with missing destination should not be included.
        EventFactory::new()->published()->create([
            'externalUrl' => true,
            'url' => '',
        ]);

        // External event pointing to non-entropy.fi domain should not be included.
        EventFactory::new()->published()->create([
            'externalUrl' => true,
            'url' => 'https://example.com/excluded-external-'.uniqid('', true),
        ]);

        // External event pointing to entropy.fi should be included.
        $externalUrl = 'https://entropy.fi/rave/sitemap-external-'.uniqid('', true);
        EventFactory::new()->published()->create([
            'externalUrl' => true,
            'url' => $externalUrl,
        ]);

        $crawler = $this->client->request('GET', '/sitemap.xml');
        self::assertInstanceOf(Crawler::class, $crawler);
        $this->assertResponseIsSuccessful();
        self::assertSame('text/xml; charset=UTF-8', $this->client->getResponse()->headers->get('Content-Type'));

        $xml = (string) $this->client->getResponse()->getContent();
        self::assertNotSame('', $xml);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        $locNodes = $xpath->query('/sm:urlset/sm:url/sm:loc');
        self::assertNotFalse($locNodes);

        $locs = [];
        foreach ($locNodes as $node) {
            $locs[] = trim((string) $node->textContent);
        }

        $router = static::getContainer()->get('cmf_routing.router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        $rootFiPath = (string) ($pageRootFi->getSite()?->getRelativePath() ?? '').$pageRootFi->getUrl();
        $l2FiPath = (string) ($pageLv2Fi->getSite()?->getRelativePath() ?? '').$pageLv2Fi->getUrl();
        $l3FiPath = (string) ($pageLv3Fi->getSite()?->getRelativePath() ?? '').$pageLv3Fi->getUrl();

        $rootFi = $router->generate('page_slug', ['path' => $rootFiPath], UrlGeneratorInterface::ABSOLUTE_URL);
        $l2Fi = $router->generate('page_slug', ['path' => $l2FiPath], UrlGeneratorInterface::ABSOLUTE_URL);
        $l3Fi = $router->generate('page_slug', ['path' => $l3FiPath], UrlGeneratorInterface::ABSOLUTE_URL);

        $rootEnPath = (string) ($pageRootEn->getSite()?->getRelativePath() ?? '').$pageRootEn->getUrl();
        $l3EnPath = (string) ($pageLv3En->getSite()?->getRelativePath() ?? '').$pageLv3En->getUrl();

        $rootEn = $router->generate('page_slug', ['path' => $rootEnPath], UrlGeneratorInterface::ABSOLUTE_URL);
        $l3En = $router->generate('page_slug', ['path' => $l3EnPath], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertContains($rootFi, $locs);
        self::assertContains($l2Fi, $locs);
        self::assertContains($l3Fi, $locs);

        $year = $event->getEventDate()->format('Y');
        $eventFi = $router->generate('entropy_event_slug', [
            'year' => $year,
            'slug' => $event->getUrl(),
            '_locale' => 'fi',
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        self::assertContains($eventFi, $locs);
        self::assertContains($externalUrl, $locs);

        // Ensure no empty loc entries.
        self::assertNotContains('', $locs);
        // Ensure we don't leak locale through query parameters.
        self::assertStringNotContainsString('?_locale=', $xml);

        // External events have no EN alternate (no localized page exists).
        $externalUrlNode = $xpath->query('/sm:urlset/sm:url[sm:loc="'.$externalUrl.'"]');
        self::assertNotFalse($externalUrlNode);
        self::assertSame(1, $externalUrlNode->length);

        $externalEnAlt = $xpath->query('xhtml:link[@hreflang="en"]', $externalUrlNode->item(0));
        self::assertNotFalse($externalEnAlt);
        self::assertSame(0, $externalEnAlt->length);

        // Verify alternate hreflang links exist for a level-3 menu page.
        $l3Url = $xpath->query('/sm:urlset/sm:url[sm:loc="'.$l3Fi.'"]');
        self::assertNotFalse($l3Url);
        self::assertSame(1, $l3Url->length);

        $altLinks = $xpath->query('xhtml:link', $l3Url->item(0));
        self::assertNotFalse($altLinks);
        self::assertSame(2, $altLinks->length);

        $hrefsByLang = [];
        foreach ($altLinks as $link) {
            $lang = $link->attributes?->getNamedItem('hreflang')?->nodeValue;
            $href = $link->attributes?->getNamedItem('href')?->nodeValue;
            if (\is_string($lang) && \is_string($href)) {
                $hrefsByLang[$lang] = $href;
            }
        }

        self::assertSame($l3Fi, $hrefsByLang['fi'] ?? null);
        self::assertSame($l3En, $hrefsByLang['en'] ?? null);
    }

    private function asPage(object $pageOrProxy): PageInterface
    {
        if ($pageOrProxy instanceof Proxy) {
            $pageOrProxy = $pageOrProxy->object();
        }
        self::assertInstanceOf(PageInterface::class, $pageOrProxy);

        return $pageOrProxy;
    }
}
