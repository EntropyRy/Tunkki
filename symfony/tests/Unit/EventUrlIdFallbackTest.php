<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Event;
use PHPUnit\Framework\TestCase;

/**
 * Tests numeric ID fallback branches in Event::getUrlByLang().
 *
 * Fallback condition (from entity):
 *   if (($this->url === null || $this->url === "" || $this->url === "0") && !$this->externalUrl) {
 *       return "https://entropy.fi" . $event . $this->id;
 *   }
 *
 * Where:
 *   - For Finnish (fi) the base segment is "/tapahtuma/{id}"
 *   - For English (en or any non-fi) the base segment is "/en/event/{id}"
 *
 * We verify all three "empty" triggers (null, empty string, "0") produce the correct
 * language-specific numeric URL, and that a non-empty slug uses the year+slug pattern.
 */
final class EventUrlIdFallbackTest extends TestCase
{
    /**
     * Helper to create an Event with minimal needed fields and a fixed ID.
     *
     * @param mixed $rawUrl Value passed to setUrl (null, "", "0", or slug)
     */
    private function makeEvent(mixed $rawUrl, int $id = 777): Event
    {
        $e = new Event();
        $e->setName('Test EN');
        $e->setNimi('Test FI');
        $e->setType('event');
        $e->setEventDate(new \DateTimeImmutable('+5 days'));
        $e->setPublishDate(new \DateTimeImmutable('-1 hour'));
        $e->setPublished(true);
        $e->setExternalUrl(false);
        $e->setUrl($rawUrl);

        // Reflection-set private id so fallback has a numeric value.
        $ref = new \ReflectionProperty($e, 'id');
        $ref->setAccessible(true);
        $ref->setValue($e, $id);

        return $e;
    }

    public function testFinnishFallbackWithNullSlug(): void
    {
        $e = $this->makeEvent(null, 101);
        $fi = $e->getUrlByLang('fi');
        self::assertSame('/tapahtuma/101', parse_url($fi, \PHP_URL_PATH));
    }

    public function testFinnishFallbackWithEmptySlug(): void
    {
        $e = $this->makeEvent('', 102);
        $fi = $e->getUrlByLang('fi');
        self::assertSame('/tapahtuma/102', parse_url($fi, \PHP_URL_PATH));
    }

    public function testEnglishFallbackWithZeroStringSlug(): void
    {
        $e = $this->makeEvent('0', 103);
        $en = $e->getUrlByLang('en');
        self::assertSame('/en/event/103', parse_url($en, \PHP_URL_PATH));
    }

    public function testNonFallbackSlugUsesYearPatternFi(): void
    {
        $e = $this->makeEvent('my-event-slug', 104);
        $year = $e->getEventDate()->format('Y');
        $fi = $e->getUrlByLang('fi');
        self::assertSame("/{$year}/my-event-slug", parse_url($fi, \PHP_URL_PATH));
    }

    public function testNonFallbackSlugUsesYearPatternEn(): void
    {
        $e = $this->makeEvent('another-slug', 105);
        $year = $e->getEventDate()->format('Y');
        $en = $e->getUrlByLang('en');
        self::assertSame("/en/{$year}/another-slug", parse_url($en, \PHP_URL_PATH));
    }
}
