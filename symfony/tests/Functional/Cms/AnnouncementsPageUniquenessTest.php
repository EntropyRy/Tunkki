<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cms;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;

final class AnnouncementsPageUniquenessTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure SiteRequest wrapping and CMS baseline are active for each test
        $this->initSiteAwareClient();
        $this->ensureCmsBaseline();
    }

    public function testAnnouncementsPagesReturn200ForFiAndEn(): void
    {
        $client = $this->client;
        // Seed a published 'announcement' event so the announcements page renders content
        $slug = 'announce-'.bin2hex(random_bytes(4));
        EventFactory::new()->published()->create([
            'type' => 'announcement',
            'url' => $slug,
        ]);

        // Finnish: /tiedotukset
        $client->request('GET', '/tiedotukset');
        $status = $client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $client->request('GET', $loc);
            }
        }
        self::assertResponseIsSuccessful('Expected 2xx for /tiedotukset');

        // English: /en/announcements
        $client->request('GET', '/en/announcements');
        $status = $client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $client->request('GET', $loc);
            }
        }
        self::assertResponseIsSuccessful('Expected 2xx for /en/announcements');
    }

    public function testExactlyOneAnnouncementsPagePerSite(): void
    {
        $em = $this->em();
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        /** @var SonataPageSite|null $fi */
        $fi = $siteRepo->findOneBy(['locale' => 'fi']);
        /** @var SonataPageSite|null $en */
        $en = $siteRepo->findOneBy(['locale' => 'en']);

        self::assertNotNull($fi, 'Finnish site should exist in baseline.');
        self::assertNotNull($en, 'English site should exist in baseline.');

        $fiAnnouncements = $pageRepo->findBy(['site' => $fi, 'url' => '/tiedotukset']);
        $enAnnouncements = $pageRepo->findBy(['site' => $en, 'url' => '/announcements']);

        self::assertSame(
            1,
            \count($fiAnnouncements),
            'Exactly one /tiedotukset (FI) page must exist for the FI site.'
        );
        self::assertSame(
            1,
            \count($enAnnouncements),
            'Exactly one /announcements (EN) page must exist for the EN site.'
        );

        // Soft normalization checks (do not fail the test suite if missing — keep them as expectations)
        $fiPage = $fiAnnouncements[0] ?? null;
        $enPage = $enAnnouncements[0] ?? null;

        if ($fiPage instanceof SonataPagePage) {
            self::assertSame('page_slug', $fiPage->getRouteName(), 'FI announcements page should use routeName=page_slug.');
            self::assertSame('annnouncements', $fiPage->getTemplateCode(), 'FI announcements page should use templateCode=annnouncements.');
            self::assertSame('entropy.page.announcementspage', $fiPage->getType(), 'FI announcements page type mismatch.');
            if (method_exists($fiPage, 'getPageAlias')) {
                self::assertSame('_page_alias_announcements_fi', (string) $fiPage->getPageAlias(), 'FI page alias mismatch.');
            }
        }

        if ($enPage instanceof SonataPagePage) {
            self::assertSame('page_slug', $enPage->getRouteName(), 'EN announcements page should use routeName=page_slug.');
            self::assertSame('annnouncements', $enPage->getTemplateCode(), 'EN announcements page should use templateCode=annnouncements.');
            self::assertSame('entropy.page.announcementspage', $enPage->getType(), 'EN announcements page type mismatch.');
            if (method_exists($enPage, 'getPageAlias')) {
                self::assertSame('_page_alias_announcements_en', (string) $enPage->getPageAlias(), 'EN page alias mismatch.');
            }
        }
    }
}
