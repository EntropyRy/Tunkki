<?php

declare(strict_types=1);

namespace App\Tests\Functional\Profile;

use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for artist profile sync to EventArtistInfo clones.
 *
 * Tests cover:
 *  - Sync section visibility based on active signups
 *  - Selective sync of artist data to event signups
 *  - Past event signups excluded from sync options
 *  - Flash message feedback after sync
 *  - Security: only owner can edit artist profile
 */
final class ArtistSignupSyncTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testEditFormHidesSyncSectionForSingleSignup(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Test Artist',
            'type' => 'DJ',
            'hardware' => 'CDJs',
        ]);

        // Create a single future event with artist signup (auto-syncs, no UI)
        $futureEvent = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Future Test Event',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($futureEvent)->forArtist($artist)->create();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        $this->assertResponseIsSuccessful();
        // Single signup: no sync section shown (auto-syncs silently)
        $this->client->assertSelectorNotExists('input[name="sync_signups[]"]');
    }

    public function testEditFormShowsCheckboxesForMultipleSignups(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Multi Signup Artist',
            'type' => 'DJ',
            'hardware' => 'CDJs',
        ]);

        // Create TWO future events with signups
        $event1 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Event One',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($event1)->forArtist($artist)->create();

        $event2 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Event Two',
            'eventDate' => new \DateTimeImmutable('+60 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($event2)->forArtist($artist)->create();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $crawler = $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        $this->assertResponseIsSuccessful();
        // Multiple signups: shows checkboxes
        $checkboxes = $crawler->filter('input[name="sync_signups[]"]');
        $this->assertSame(2, $checkboxes->count());
    }

    public function testEditFormExcludesPastEventSignupsFromSyncSection(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Historical Artist',
            'type' => 'DJ',
            'hardware' => 'CDJs',
        ]);

        // Create a past event (should not appear in sync section)
        $pastEvent = EventFactory::new()->published()->past()->create([
            'name' => 'Past Event',
        ]);
        EventArtistInfoFactory::new()->forEvent($pastEvent)->forArtist($artist)->create();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        $this->assertResponseIsSuccessful();
        // Past event signups should not show sync section
        $this->client->assertSelectorNotExists('input[name="sync_signups[]"]');
    }

    public function testSingleSignupAutoSyncsOnFormSubmit(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Original Name',
            'type' => 'DJ',
            'hardware' => 'CDJs',
            'bio' => 'Original bio',
            'bioEn' => 'Original bio EN',
            'genre' => 'Techno',
        ]);

        // Create a single future event with artist signup (will auto-sync)
        $futureEvent = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Auto Sync Event',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);
        $signup = EventArtistInfoFactory::new()->forEvent($futureEvent)->forArtist($artist)->create();
        $signupId = $signup->getId();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $crawler = $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        // Submit form with updated data - no checkbox needed for single signup
        $form = $crawler->filter('form input[type="submit"]')->form();
        $form['artist[name]'] = 'Updated Name';
        $form['artist[type]'] = 'DJ';
        $form['artist[hardware]'] = 'Auto Updated Hardware';
        $form['artist[bio]'] = 'Updated bio';
        $form['artist[bioEn]'] = 'Updated bio EN';
        $form['artist[genre]'] = 'House';

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify artist clone was auto-synced
        $this->em()->clear();
        $updatedSignup = $this->em()->find(\App\Entity\EventArtistInfo::class, $signupId);
        $this->assertNotNull($updatedSignup);

        $updatedClone = $updatedSignup->getArtistClone();
        $this->assertNotNull($updatedClone);

        // Clone should have new data
        $this->assertSame('Auto Updated Hardware', $updatedClone->getHardware());
        $this->assertSame('Updated bio', $updatedClone->getBio());
        $this->assertSame('Updated bio EN', $updatedClone->getBioEn());
        $this->assertSame('House', $updatedClone->getGenre());
    }

    public function testSelectingSyncCheckboxesSyncsArtistData(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Original Name',
            'type' => 'DJ',
            'hardware' => 'CDJs',
            'bio' => 'Original bio',
            'bioEn' => 'Original bio EN',
            'genre' => 'Techno',
        ]);

        // Create TWO future events (multiple = checkbox mode)
        $event1 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Sync Test Event',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);
        $signup = EventArtistInfoFactory::new()->forEvent($event1)->forArtist($artist)->create();
        $signupId = $signup->getId();

        $event2 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Other Event',
            'eventDate' => new \DateTimeImmutable('+60 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($event2)->forArtist($artist)->create();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $crawler = $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        // Submit form with updated data AND sync checkbox selected
        $form = $crawler->filter('form input[type="submit"]')->form();
        $form['artist[name]'] = 'Updated Name';
        $form['artist[type]'] = 'DJ';
        $form['artist[hardware]'] = 'Updated Hardware';
        $form['artist[bio]'] = 'Updated bio';
        $form['artist[bioEn]'] = 'Updated bio EN';
        $form['artist[genre]'] = 'House';

        // Select the sync checkbox for first signup only
        $this->client->submit($form, ['sync_signups' => [(string) $signupId]]);

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify artist clone was updated
        $this->em()->clear();
        $updatedSignup = $this->em()->find(\App\Entity\EventArtistInfo::class, $signupId);
        $this->assertNotNull($updatedSignup);

        $updatedClone = $updatedSignup->getArtistClone();
        $this->assertNotNull($updatedClone);

        // Clone should have new data
        $this->assertSame('Updated Hardware', $updatedClone->getHardware());
        $this->assertSame('Updated bio', $updatedClone->getBio());
        $this->assertSame('Updated bio EN', $updatedClone->getBioEn());
        $this->assertSame('House', $updatedClone->getGenre());
    }

    public function testNotSelectingAnySyncCheckboxDoesNotSyncWithMultipleSignups(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'NoSync Artist',
            'type' => 'DJ',
            'hardware' => 'Original Hardware',
            'bio' => 'Original bio',
            'bioEn' => 'Original bio EN',
        ]);

        // Create TWO future events (multiple = checkbox mode, won't auto-sync)
        $event1 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'NoSync Test Event',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);
        $signup = EventArtistInfoFactory::new()->forEvent($event1)->forArtist($artist)->create();
        $signupId = $signup->getId();

        $event2 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Other NoSync Event',
            'eventDate' => new \DateTimeImmutable('+60 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($event2)->forArtist($artist)->create();

        // Get original clone hardware
        $originalHardware = $signup->getArtistClone()->getHardware();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $crawler = $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        // Submit form with updated data but NO sync checkboxes selected
        $form = $crawler->filter('form input[type="submit"]')->form();
        $form['artist[name]'] = 'NoSync Artist';
        $form['artist[type]'] = 'DJ';
        $form['artist[hardware]'] = 'Updated Hardware';
        $form['artist[bio]'] = 'Original bio';
        $form['artist[bioEn]'] = 'Original bio EN';

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify artist clone was NOT updated (no checkboxes selected)
        $this->em()->clear();
        $unchangedSignup = $this->em()->find(\App\Entity\EventArtistInfo::class, $signupId);
        $this->assertNotNull($unchangedSignup);
        $this->assertSame($originalHardware, $unchangedSignup->getArtistClone()->getHardware());
    }

    public function testCannotEditAnotherMembersArtist(): void
    {
        $owner = MemberFactory::new()->active()->create();
        $intruder = MemberFactory::new()->active()->create();

        $artist = ArtistFactory::new()->withMember($owner)->create([
            'name' => 'Protected Artist',
            'type' => 'DJ',
        ]);

        // Login as intruder
        $this->loginAsMember($intruder->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        // Should be denied access
        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('localeProvider')]
    public function testSyncSectionVisibleInBothLocales(string $locale): void
    {
        $member = MemberFactory::new()->active()->with(['locale' => $locale])->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Bilingual Artist',
            'type' => 'DJ',
            'hardware' => 'CDJs',
        ]);

        // Create TWO signups so sync section is visible
        $event1 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Bilingual Event 1',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($event1)->forArtist($artist)->create();

        $event2 = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Bilingual Event 2',
            'eventDate' => new \DateTimeImmutable('+60 days'),
        ]);
        EventArtistInfoFactory::new()->forEvent($event2)->forArtist($artist)->create();

        $this->loginAsMember($member->getEmail());

        $path = 'en' === $locale
            ? "/en/profile/artist/{$artist->getId()}/edit"
            : "/profiili/artisti/{$artist->getId()}/muokkaa";

        $crawler = $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $checkboxes = $crawler->filter('input[name="sync_signups[]"]');
        $this->assertSame(2, $checkboxes->count());
    }

    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }

    public function testSyncIgnoresSignupsBelongingToOtherArtists(): void
    {
        $member = MemberFactory::new()->active()->create();
        $myArtist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'My Artist',
            'type' => 'DJ',
            'hardware' => 'My Hardware',
            'bio' => 'My bio',
            'bioEn' => 'My bio EN',
        ]);

        // Create another artist belonging to same member (but different artist entity)
        $otherArtist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Other Artist',
            'type' => 'Live',
            'hardware' => 'Other Hardware',
        ]);

        $futureEvent = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Cross Artist Event',
            'eventDate' => new \DateTimeImmutable('+30 days'),
        ]);

        // Signup belongs to OTHER artist
        $otherSignup = EventArtistInfoFactory::new()->forEvent($futureEvent)->forArtist($otherArtist)->create();
        $originalHardware = $otherSignup->getArtistClone()->getHardware();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        // Get CSRF token from form
        $crawler = $this->client->request('GET', "/en/profile/artist/{$myArtist->getId()}/edit");
        $token = $crawler->filter('input[name="artist[_token]"]')->attr('value');

        // Submit raw POST trying to sync OTHER artist's signup (bypasses form validation)
        $this->client->request('POST', "/en/profile/artist/{$myArtist->getId()}/edit", [
            'artist' => [
                'name' => 'My Artist',
                'type' => 'DJ',
                'hardware' => 'Attempted Cross-Sync',
                'bio' => 'My bio',
                'bioEn' => 'My bio EN',
                '_token' => $token,
            ],
            'sync_signups' => [(string) $otherSignup->getId()], // Other artist's signup
        ]);

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify other artist's signup was NOT synced
        $this->em()->clear();
        $unchangedSignup = $this->em()->find(\App\Entity\EventArtistInfo::class, $otherSignup->getId());
        $this->assertSame($originalHardware, $unchangedSignup->getArtistClone()->getHardware());
    }
}
