<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Rental\Inventory\Item;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class InventoryControllerTest extends FixturesWebTestCase
{
    #[DataProvider('localeProvider')]
    public function testNeedsFixingPageRendersInBothLocales(string $locale, string $path): void
    {
        $brokenName = 'Broken item '.bin2hex(random_bytes(3));
        $okName = 'Working item '.bin2hex(random_bytes(3));
        $item = new Item();
        $item->setName($brokenName);
        $item->setNeedsFixing(true);
        $okItem = new Item();
        $okItem->setName($okName);
        $okItem->setNeedsFixing(false);
        $this->em()->persist($item);
        $this->em()->persist($okItem);
        $this->em()->flush();

        $this->seedClientHome($locale);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $headings = $this->client->getCrawler()->filter('h3');
        $this->assertGreaterThan(0, $headings->count());
        $headingTexts = $headings->each(static fn ($node): string => $node->text());
        $this->assertContains($brokenName, $headingTexts);
        $this->assertNotContains($okName, $headingTexts);
    }

    public static function localeProvider(): array
    {
        return [
            ['fi', '/inventaario/korjattavat'],
            ['en', '/en/inventory/needs-fixing'],
        ];
    }
}
