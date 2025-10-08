<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cms;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Tests\_Base\FixturesWebTestCase;

final class StreamPageUniquenessTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure SiteRequest wrapping and CMS baseline are active for each test
        $this->initSiteAwareClient();
        $this->ensureCmsBaseline();
    }

    public function testStreamPagesReturn200ForFiAndEn(): void
    {
        $client = $this->client;

        // Finnish: /stream
        $client->request('GET', '/stream');
        $status = $client->getResponse()->getStatusCode();
        if (in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $client->request('GET', $loc);
            }
        }
        self::assertResponseIsSuccessful('Expected 2xx for /stream');

        // English: /en/stream
        $client->request('GET', '/en/stream');
        $status = $client->getResponse()->getStatusCode();
        if (in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $client->request('GET', $loc);
            }
        }
        self::assertResponseIsSuccessful('Expected 2xx for /en/stream');
    }

    public function testExactlyOneStreamPagePerSite(): void
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

        $fiStreamPages = $pageRepo->findBy(['site' => $fi, 'url' => '/stream']);
        $enStreamPages = $pageRepo->findBy(['site' => $en, 'url' => '/stream']);

        self::assertSame(
            1,
            count($fiStreamPages),
            'Exactly one /stream page must exist for FI site.'
        );
        self::assertSame(
            1,
            count($enStreamPages),
            'Exactly one /stream page must exist for EN site.'
        );
    }
}
