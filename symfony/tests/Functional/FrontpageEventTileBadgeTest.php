<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\UniqueValueTrait;

final class FrontpageEventTileBadgeTest extends FixturesWebTestCase
{
    use UniqueValueTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    public function testCancelledEventUsesCancelledBadgeInFrontpageTile(): void
    {
        $cancelled = EventFactory::new()
            ->published()
            ->create([
                'url' => $this->uniqueSlug('frontpage-cancelled-event'),
                'name' => 'Cancelled Event Frontpage',
                'nimi' => 'Peruttu etusivutapahtuma',
                'cancelled' => true,
            ]);

        $active = EventFactory::new()
            ->published()
            ->create([
                'url' => $this->uniqueSlug('frontpage-active-event'),
                'name' => 'Active Event Frontpage',
                'nimi' => 'Aktiivinen etusivutapahtuma',
            ]);

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $cancelledTile = $this->findTileByEventSlug(
            (int) $cancelled->getEventDate()->format('Y'),
            $cancelled->getUrl() ?? '',
        );
        self::assertSame(1, $cancelledTile->filter('.event-time-badge--cancelled')->count());
        self::assertSame(0, $cancelledTile->filter('[data-moment-target="badge"]')->count());

        $activeTile = $this->findTileByEventSlug(
            (int) $active->getEventDate()->format('Y'),
            $active->getUrl() ?? '',
        );
        self::assertSame(0, $activeTile->filter('.event-time-badge--cancelled')->count());
        self::assertSame(1, $activeTile->filter('[data-moment-target="badge"]')->count());
    }

    private function findTileByEventSlug(int $year, string $slug): \Symfony\Component\DomCrawler\Crawler
    {
        $tile = $this->client->getCrawler()->filterXPath(
            \sprintf(
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' event_tile ')][.//a[contains(@href, '/%d/%s')]]",
                $year,
                $slug,
            ),
        );

        self::assertSame(1, $tile->count(), \sprintf('Expected one frontpage tile for event slug "%s".', $slug));

        return $tile;
    }
}
