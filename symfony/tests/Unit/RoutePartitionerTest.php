<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sonata\PageBundle\Route\RoutePartitioner;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Unit tests for the custom RoutePartitioner logic.
 *
 * Goals:
 *  - Validate neutral vs localized classification (baseName.locale)
 *  - Verify alias mapping (base -> localized full name)
 *  - Ensure neutral routes are merged into locale collections unless shadowed
 *  - Confirm whitelist (allowed locales) prunes unexpected suffixes
 */
final class RoutePartitionerTest extends TestCase
{
    private function buildCollection(array $definitions): RouteCollection
    {
        $c = new RouteCollection();
        foreach ($definitions as $name => $path) {
            $c->add($name, new Route($path));
        }

        return $c;
    }

    public function testBasicPartitioningAndAliasMapping(): void
    {
        $all = $this->buildCollection([
            'homepage' => '/',
            'entropy_event_shop.fi' => '/{year}/{slug}/kauppa',
            'entropy_event_shop.en' => '/{year}/{slug}/shop',
            'api_status' => '/api/status',
            'profile.en' => '/en/profile',
        ]);

        $p = new RoutePartitioner();
        $p->partition($all);

        // Locales discovered
        $locales = $p->getLocales();
        sort($locales);
        self::assertSame(['en', 'fi'], $locales, 'Should discover fi and en locales.');

        // Alias lookups
        self::assertSame('entropy_event_shop.fi', $p->getAlias('entropy_event_shop', 'fi'));
        self::assertSame('entropy_event_shop.en', $p->getAlias('entropy_event_shop', 'en'));
        self::assertNull($p->getAlias('entropy_event_shop', 'sv'));

        // Neutral collection should contain non-suffixed names
        $neutral = $p->getNeutralCollection();
        self::assertNotNull($neutral->get('homepage'));
        self::assertNotNull($neutral->get('api_status'));
        // profile.en is localized; not neutral
        self::assertNull($neutral->get('profile.en'));

        // Locale collection merge: each locale should have its localized routes + neutral (unless shadowed)
        $fiCollection = $p->getLocaleCollection('fi');
        self::assertNotNull($fiCollection?->get('homepage'), 'Neutral route should be present in fi collection.');
        self::assertNotNull($fiCollection?->get('entropy_event_shop.fi'));
        self::assertNull($fiCollection?->get('entropy_event_shop.en'));

        $enCollection = $p->getLocaleCollection('en');
        self::assertNotNull($enCollection?->get('homepage'), 'Neutral route should be present in en collection.');
        self::assertNotNull($enCollection?->get('entropy_event_shop.en'));
        self::assertNotNull($enCollection?->get('profile.en'), 'English localized profile route present.');
    }

    public function testAllowedLocalesWhitelistPrunesUnexpectedSuffixes(): void
    {
        $all = $this->buildCollection([
            'alpha.en' => '/en/alpha',
            'alpha.fi' => '/alpha',
            'alpha.internal' => '/internal-alpha',
            'beta.sv' => '/sv/beta',
            'plain' => '/plain',
        ]);

        $p = new RoutePartitioner();
        // Only treat en & fi as localized; others become neutral
        $p->setAllowedLocales(['en', 'fi']);
        $p->partition($all);

        $locales = $p->getLocales();
        sort($locales);
        self::assertSame(['en', 'fi'], $locales, 'Only whitelisted locales should be recognized.');

        // internal & sv suffix routes should have been classified as neutral now
        $neutral = $p->getNeutralCollection();
        self::assertNotNull($neutral->get('alpha.internal'), 'Non-whitelisted suffix becomes neutral.');
        self::assertNotNull($neutral->get('beta.sv'), 'Non-whitelisted locale becomes neutral.');

        // Aliases only for en/fi
        self::assertSame('alpha.en', $p->getAlias('alpha', 'en'));
        self::assertSame('alpha.fi', $p->getAlias('alpha', 'fi'));
        self::assertNull($p->getAlias('alpha', 'sv'));
        self::assertNull($p->getAlias('alpha', 'internal'));

        $enCollection = $p->getLocaleCollection('en');
        $fiCollection = $p->getLocaleCollection('fi');

        self::assertNotNull($enCollection?->get('alpha.en'));
        self::assertNotNull($fiCollection?->get('alpha.fi'));

        // Neutral 'plain' present in both
        self::assertNotNull($enCollection?->get('plain'));
        self::assertNotNull($fiCollection?->get('plain'));

        // Ensure internal/sv treated as neutral so they appear too
        self::assertNotNull($enCollection?->get('alpha.internal'));
        self::assertNotNull($fiCollection?->get('beta.sv'));
    }

    public function testIdempotentPartitionCall(): void
    {
        $all = $this->buildCollection([
            'r.fi' => '/fi/r',
            'r.en' => '/en/r',
        ]);

        $p = new RoutePartitioner();
        $p->partition($all);
        $firstLocales = $p->getLocales();

        // Second call should no-op without errors
        $p->partition($all);
        $secondLocales = $p->getLocales();

        self::assertSame($firstLocales, $secondLocales, 'Partitioner should be idempotent.');
    }

    public function testMalformedSuffixFallsBackToNeutral(): void
    {
        $all = $this->buildCollection([
            'bad.' => '/oops',
            'no/slash.fi' => '/weird', // name has slash but that is okay; classification uses last dot only
            'ok.en' => '/en/ok',
        ]);

        $p = new RoutePartitioner();
        $p->partition($all);

        $neutral = $p->getNeutralCollection();
        self::assertNotNull($neutral->get('bad.'), 'Empty suffix route should be neutral.');

        // 'no/slash.fi' => suffix 'fi' (valid); ensure classification works even if base contains slash-like text
        self::assertSame('no/slash.fi', $p->getAlias('no/slash', 'fi'));
        self::assertNull($p->getAlias('no/slash', 'en'));

        $locales = $p->getLocales();
        sort($locales);
        self::assertSame(['en', 'fi'], $locales);
    }
}
