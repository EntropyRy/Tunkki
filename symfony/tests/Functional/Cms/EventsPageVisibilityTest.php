<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cms;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LocaleDataProviderTrait;
use App\Tests\Support\LoginHelperTrait;

/**
 * Functional coverage for Events page visibility rules:
 * - Anonymous users: only public events (published=true and publishDate <= now)
 * - Active members: all non-announcement events (drafts and future-publishDate included)
 *
 * Notes on assertions:
 * - We prefer structural selectors, but the Events page template does not currently expose stable
 *   data-test hooks for individual event items. As a temporary micro-invariant, we assert presence/
 *   absence of known slugs in the response body. Replace with structural assertions once hooks exist.
 */
final class EventsPageVisibilityTest extends FixturesWebTestCase
{
    use LocaleDataProviderTrait;
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Sonata multisite SiteRequest wrapping + deterministic site baseline
        $this->initSiteAwareClient();
        $this->ensureCmsBaseline();
    }

    /**
     * Anonymous users should only see events that are publicly published.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideLocales')]
    public function testAnonymousSeesOnlyPublicEvents(string $locale): void
    {
        // Arrange: create events of default type ('event' != 'announcement')
        $visible = EventFactory::new()->published()->create([
            'url' => 'events-page-visible',
        ]);
        EventFactory::new()->published()->create([
            'url' => 'events-page-future',
            'publishDate' => new \DateTimeImmutable('+1 day'),
        ]);
        EventFactory::new()->unpublished()->create([
            'url' => 'events-page-draft',
        ]);

        // Act
        $this->client()->request('GET', $this->eventsPathForLocale($locale));

        // Assert
        self::assertResponseIsSuccessful();

        // Temporary micro-invariant assertions (replace with structural hooks when available)
        $content = $this->client()->getResponse()->getContent() ?: '';
        self::assertStringContainsString('events-page-visible', $content, 'Publicly published event must be listed for anonymous users.');
        self::assertStringNotContainsString('events-page-draft', $content, 'Draft event must not be listed for anonymous users.');
        self::assertStringNotContainsString('events-page-future', $content, 'Future publishDate event must not be listed for anonymous users.');
    }

    /**
     * Active members should see all non-announcement events (incl. drafts and future-publishDate).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideLocales')]
    public function testActiveMemberSeesAllEvents(string $locale): void
    {
        // Arrange
        $visible = EventFactory::new()->published()->create([
            'url' => 'events-page-visible-am',
        ]);
        EventFactory::new()->published()->create([
            'url' => 'events-page-future-am',
            'publishDate' => new \DateTimeImmutable('+2 days'),
        ]);
        EventFactory::new()->unpublished()->create([
            'url' => 'events-page-draft-am',
        ]);

        // Log in as an active member (helper ensures Member::getIsActiveMember() === true)

        $this->loginAsActiveMember();

        // Act
        $this->client()->request('GET', $this->eventsPathForLocale($locale));

        // Assert
        self::assertResponseIsSuccessful();

        // Temporary micro-invariant assertions (replace with structural hooks when available)
        $content = $this->client()->getResponse()->getContent() ?: '';
        self::assertStringContainsString('events-page-visible-am', $content, 'Published event must be listed for active members.');
        self::assertStringContainsString('events-page-draft-am', $content, 'Draft event must be listed for active members.');
        self::assertStringContainsString('events-page-future-am', $content, 'Future publishDate event must be listed for active members.');
    }

    /**
     * Data provider for locales (Finnish default without prefix; English under /en).
     *
     * @return iterable<string[]>
     */
    public static function provideLocales(): iterable
    {
        yield ['fi'];
        yield ['en'];
    }

    /**
     * Resolve the events page path for the given locale.
     * - FI: /tapahtumat
     * - EN: /en/events.
     */
    private function eventsPathForLocale(string $locale): string
    {
        return 'en' === $locale ? '/en/events' : '/tapahtumat';
    }
}
