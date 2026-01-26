<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\DomCrawler\Crawler;

/**
 * MetaAssertionTrait.
 *
 * Reusable high‑level assertion helpers for:
 *  - Meta tag presence & content
 *  - <html lang> attribute checks
 *  - Canonical / alternate link structure
 *  - Case‑insensitive substring checks
 *  - Duplicate / conflicting tag detection
 *
 * Rationale:
 * Centralizes structural HTML assertions to:
 *  - Reduce brittle raw string searches in tests
 *  - Encourage semantic, intention‑revealing checks
 *  - Provide consistent failure messages across the suite
 *
 * Typical usage inside a WebTestCase descendant:
 *
 *   use App\Tests\Support\MetaAssertionTrait;
 *
 *   $crawler = $client->request('GET', '/en/page');
 *   $this->assertHtmlLang($crawler, 'en');
 *   $this->assertMetaTagExists($crawler, 'property', 'og:title');
 *   $this->assertMetaTagContentContains($crawler, 'property', 'og:description', 'My Site');
 *   $this->assertCanonicalLink($crawler, 'https://example.test/en/page');
 *
 * All methods are deliberately prefixed with assert* for PHPUnit integration.
 */
trait MetaAssertionTrait
{
    /**
     * Assert that <html lang="..."> matches the expected locale code.
     * Falls back with an informative message if missing (rather than silently passing),
     * because explicit language markup improves accessibility & SEO.
     */
    protected function assertHtmlLang(Crawler $crawler, string $expected): void
    {
        $nodes = $crawler->filter(\sprintf('html[lang="%s"]', $expected));
        \PHPUnit\Framework\Assert::assertGreaterThan(
            0,
            $nodes->count(),
            \sprintf('Expected <html lang="%s"> to be present.', $expected)
        );
    }

    /**
     * Assert existence of a meta tag like: <meta {attribute}="{value}" ...>.
     */
    protected function assertMetaTagExists(Crawler $crawler, string $attribute, string $value, string $message = ''): void
    {
        $selector = \sprintf('meta[%s="%s"]', $attribute, $value);
        $count = $crawler->filter($selector)->count();
        \PHPUnit\Framework\Assert::assertGreaterThan(
            0,
            $count,
            '' !== $message ? $message : \sprintf('Expected meta tag %s (found none).', $selector)
        );
    }

    /**
     * Assert that a meta tag exists AND its content attribute is non‑empty.
     * Returns the content for optional chaining in a test.
     */
    protected function assertMetaTagContentNotEmpty(Crawler $crawler, string $attribute, string $value): string
    {
        $selector = \sprintf('meta[%s="%s"]', $attribute, $value);
        $node = $crawler->filter($selector)->first();
        \PHPUnit\Framework\Assert::assertGreaterThan(
            0,
            $node->count(),
            \sprintf('Expected meta tag %s not found.', $selector)
        );

        $content = trim((string) $node->attr('content'));
        \PHPUnit\Framework\Assert::assertNotSame(
            '',
            $content,
            \sprintf('Meta tag %s has empty content attribute.', $selector)
        );

        return $content;
    }

    /**
     * Assert that a meta tag's content attribute contains a given substring (case‑insensitive).
     */
    protected function assertMetaTagContentContains(
        Crawler $crawler,
        string $attribute,
        string $value,
        string $expectedSubstring,
    ): void {
        $content = $this->assertMetaTagContentNotEmpty($crawler, $attribute, $value);
        \PHPUnit\Framework\Assert::assertTrue(
            str_contains(mb_strtolower($content), mb_strtolower($expectedSubstring)),
            \sprintf(
                'Meta %s="%s" content did not contain substring "%s". Actual: "%s"',
                $attribute,
                $value,
                $expectedSubstring,
                $content
            )
        );
    }

    /**
     * Assert that only one meta tag with the given attribute/value exists (no duplicates).
     */
    protected function assertSingleMetaTag(Crawler $crawler, string $attribute, string $value): void
    {
        $selector = \sprintf('meta[%s="%s"]', $attribute, $value);
        $count = $crawler->filter($selector)->count();
        \PHPUnit\Framework\Assert::assertSame(
            1,
            $count,
            \sprintf('Expected exactly 1 %s but found %d.', $selector, $count)
        );
    }

    /**
     * Assert a canonical link reference: <link rel="canonical" href="...">.
     */
    protected function assertCanonicalLink(Crawler $crawler, string $expectedHref): void
    {
        $nodes = $crawler->filter('link[rel="canonical"]');
        \PHPUnit\Framework\Assert::assertGreaterThan(
            0,
            $nodes->count(),
            'Expected a canonical <link rel="canonical"> element.'
        );

        $hrefs = [];
        $nodes->each(static function (Crawler $n) use (&$hrefs): void {
            $href = trim((string) $n->attr('href'));
            if ('' !== $href) {
                $hrefs[] = $href;
            }
        });

        \PHPUnit\Framework\Assert::assertContains(
            $expectedHref,
            $hrefs,
            \sprintf(
                'Canonical link mismatch. Expected "%s" among: [%s]',
                $expectedHref,
                implode(', ', $hrefs)
            )
        );
    }

    /**
     * Assert presence of hreflang alternates: e.g.
     * <link rel="alternate" hreflang="en" href="...">.
     *
     * $expected is an associative array: ['en' => 'https://…/en/page', 'fi' => 'https://…/fi/page']
     */
    protected function assertAlternateHreflangSet(Crawler $crawler, array $expected): void
    {
        $found = [];

        $crawler->filter('link[rel="alternate"][hreflang]')->each(
            static function (Crawler $node) use (&$found): void {
                $lang = (string) $node->attr('hreflang');
                $href = (string) $node->attr('href');
                if ('' !== $lang && '' !== $href) {
                    $found[$lang] = $href;
                }
            }
        );

        foreach ($expected as $lang => $href) {
            \PHPUnit\Framework\Assert::assertArrayHasKey(
                $lang,
                $found,
                \sprintf('Missing alternate hreflang="%s" entry.', $lang)
            );
            \PHPUnit\Framework\Assert::assertSame(
                $href,
                $found[$lang],
                \sprintf(
                    'Unexpected href for hreflang="%s". Expected "%s" got "%s".',
                    $lang,
                    $href,
                    $found[$lang]
                )
            );
        }
    }

    /**
     * Assert no duplicate meta tags for a content attribute group:
     * e.g. more than one og:description or description tag.
     */
    protected function assertNoDuplicateMetaTags(Crawler $crawler, array $attributeValuePairs): void
    {
        foreach ($attributeValuePairs as [$attribute, $value]) {
            $selector = \sprintf('meta[%s="%s"]', $attribute, $value);
            $count = $crawler->filter($selector)->count();

            \PHPUnit\Framework\Assert::assertLessThan(
                2,
                $count,
                \sprintf('Duplicate meta tags detected for selector %s (found %d).', $selector, $count)
            );
        }
    }

    /**
     * Case-insensitive containment assertion helper (renamed to avoid clashing with final PHPUnit method).
     *
     * @deprecated prefer native PHPUnit assertStringContainsStringIgnoringCase() where possible
     */
    protected function assertStringContainsCaseInsensitive(string $needle, string $haystack, string $message = ''): void
    {
        $found = str_contains(mb_strtolower($haystack), mb_strtolower($needle));
        if (!$found) {
            throw new AssertionFailedError('' !== $message ? $message : \sprintf('Failed asserting that "%s" contains "%s" (case-insensitive).', $haystack, $needle));
        }
        \PHPUnit\Framework\Assert::assertTrue(true); // Mark assertion pass
    }

    /**
     * Assert that a meta tag exists AND does NOT contain a substring (inverse of contains).
     */
    protected function assertMetaTagContentNotContains(
        Crawler $crawler,
        string $attribute,
        string $value,
        string $unexpectedSubstring,
    ): void {
        $content = $this->assertMetaTagContentNotEmpty($crawler, $attribute, $value);
        $lower = mb_strtolower($content);
        $needle = mb_strtolower($unexpectedSubstring);
        \PHPUnit\Framework\Assert::assertFalse(
            str_contains($lower, $needle),
            \sprintf(
                'Meta %s="%s" content unexpectedly contains "%s". Actual: "%s"',
                $attribute,
                $value,
                $unexpectedSubstring,
                $content
            )
        );
    }

    /**
     * Assert that at least one of multiple candidate meta tags exists (useful when the template conditionally emits one).
     *
     * @param list<array{string,string}> $candidates Each element: [attribute, value]
     */
    protected function assertAnyMetaTagExists(Crawler $crawler, array $candidates, string $message = ''): void
    {
        foreach ($candidates as [$attribute, $value]) {
            $selector = \sprintf('meta[%s="%s"]', $attribute, $value);
            if ($crawler->filter($selector)->count() > 0) {
                \PHPUnit\Framework\Assert::assertTrue(true);

                return;
            }
        }

        $desc = implode(
            ' OR ',
            array_map(
                static fn (array $pair): string => \sprintf('%s="%s"', $pair[0], $pair[1]),
                $candidates
            )
        );

        \PHPUnit\Framework\Assert::fail(
            '' !== $message ? $message : \sprintf('Expected at least one meta tag of: (%s)', $desc)
        );
    }

    /**
     * Assert structured meta tag content EXACT match (strict).
     */
    protected function assertMetaTagContentSame(Crawler $crawler, string $attribute, string $value, string $expected): void
    {
        $selector = \sprintf('meta[%s="%s"]', $attribute, $value);
        $node = $crawler->filter($selector)->first();
        \PHPUnit\Framework\Assert::assertGreaterThan(0, $node->count(), \sprintf('Meta %s not found.', $selector));
        $actual = (string) $node->attr('content');
        \PHPUnit\Framework\Assert::assertSame(
            $expected,
            $actual,
            \sprintf('Meta %s content mismatch. Expected "%s" got "%s".', $selector, $expected, $actual)
        );
    }
}
