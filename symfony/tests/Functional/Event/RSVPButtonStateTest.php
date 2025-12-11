<?php

declare(strict_types=1);

namespace App\Tests\Functional\Event;

use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\RSVPFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * Functional tests to assert RSVP button state for logged-in users
 * based on whether they have already RSVP'd to the event.
 *
 * Scenarios:
 * - Logged-in user without RSVP sees an enabled RSVP button with link to RSVP route.
 * - Logged-in user with RSVP sees a disabled button, with a checkmark and translated "already RSVP'd" text.
 */
final class RSVPButtonStateTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testLoggedInUserWithoutRsvpSeesEnabledButton(): void
    {
        // Event with RSVP enabled and already published
        $event = EventFactory::new()->withRsvpEnabled()->create([
            'published' => true,
            'publishDate' => new \DateTimeImmutable('2024-12-01'),
        ]);

        // Login as an inactive member to avoid extra counters/visibility constraints
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $year = $event->getEventDate()->format('Y');
        $slug = $event->getUrl();

        // Visit event page
        $this->client->request('GET', "/{$year}/{$slug}");
        $this->assertResponseIsSuccessful();

        // Assert the RSVP button exists and is enabled with correct href to RSVP route
        $this->client->assertSelectorExists('#RSVP a.btn.btn-primary.w-100');
        $crawler = $this->client->getCrawler()->filter('#RSVP a.btn.btn-primary.w-100');
        $this->assertSame(
            1,
            $crawler->count(),
            'Expected one RSVP button for logged-in user without RSVP'
        );

        // Should have href to the RSVP route
        $href = $crawler->attr('href');
        $this->assertNotNull($href, 'RSVP button should have an href');
        $this->assertStringContainsString('/rsvp', $href, 'RSVP button should link to RSVP route');

        // Should not have disabled class nor aria-disabled attribute
        $class = $crawler->attr('class') ?? '';
        $this->assertStringNotContainsString('disabled', $class, 'RSVP button should not be disabled');
        $this->assertNull($crawler->attr('aria-disabled'), 'RSVP button should not have aria-disabled attribute');

        // Should contain label "RSVP" (base text)
        $this->assertStringContainsString('RSVP', $crawler->text(), 'Enabled RSVP button should contain "RSVP" label');
    }

    public function testLoggedInUserWithRsvpSeesDisabledButtonWithCheckmarkAndText(): void
    {
        // Event with RSVP enabled and already published
        $event = EventFactory::new()->withRsvpEnabled()->create([
            'published' => true,
            'publishDate' => new \DateTimeImmutable('2024-12-01'),
        ]);

        // Login as a member and create their RSVP for this event
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        RSVPFactory::new()->forEvent($event)->create([
            'member' => $member,
        ]);

        $year = $event->getEventDate()->format('Y');
        $slug = $event->getUrl();

        // Visit event page
        $this->client->request('GET', "/{$year}/{$slug}");
        $this->assertResponseIsSuccessful();

        // Assert the RSVP button exists and is disabled (no href, disabled class, aria-disabled)
        $this->client->assertSelectorExists('#RSVP a.btn.btn-primary.w-100.disabled');
        $crawler = $this->client->getCrawler()->filter('#RSVP a.btn.btn-primary.w-100.disabled');
        $this->assertSame(
            1,
            $crawler->count(),
            'Expected one disabled RSVP button for logged-in user with RSVP'
        );

        // Disabled button should not have href attribute to avoid accidental navigation
        $this->assertNull($crawler->attr('href'), 'Disabled RSVP button should not have an href');

        // Disabled state attributes/classes
        $class = $crawler->attr('class') ?? '';
        $this->assertStringContainsString('disabled', $class, 'RSVP button should be visually disabled');
        $this->assertSame('true', $crawler->attr('aria-disabled'), 'RSVP button should indicate aria-disabled');

        // Should include a checkmark and translated "already RSVP'd" message
        $text = $crawler->text();
        $this->assertStringContainsString('✓', $text, 'Disabled RSVP button should include a checkmark');

        // We cannot assert exact translation string content, but ensure "RSVP" is not present as the primary label
        // and that there is non-empty text (translation rendered).
        $this->assertNotEmpty(trim($text), 'Disabled RSVP button should have user-facing text');
        $this->assertStringNotContainsString('RSVP', $text, 'Disabled RSVP button should not show the plain "RSVP" label');
    }
}
