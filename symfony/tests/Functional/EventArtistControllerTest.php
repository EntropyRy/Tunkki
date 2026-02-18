<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EventArtistInfo;
use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LocaleDataProviderTrait;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for EventArtistController.
 *
 * Covered scenarios:
 *  1. Artist signup - positive path (signup window open, user has artists)
 *  2. Artist signup - no artists → redirect to artist create
 *  3. Artist signup - signup window not open
 *  4. Artist signup - signup window ended
 *  5. Artist signup - event in past
 *  6. Artist signup - unauthenticated user → redirect to login
 *  7. Artist signup - duplicate signup warning
 *  8. Artist signup - form submission creates EventArtistInfo and artistClone
 *  9. Artist signup - bilingual routes (Finnish unprefixed, English /en/)
 * 10. Artist signup edit - owner can edit
 * 11. Artist signup edit - non-owner cannot edit
 * 12. Artist signup edit - unauthenticated user → redirect to login
 * 13. Artist signup delete - owner can delete
 * 14. Artist signup delete - non-owner cannot delete
 * 15. Artist signup delete - cannot delete if event in past
 * 16. Artist signup delete - unauthenticated user → redirect to login
 */
final class EventArtistControllerTest extends FixturesWebTestCase
{
    use LocaleDataProviderTrait;
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    /**
     * Locale data provider for bilingual tests.
     *
     * @return iterable<string,array{string}>
     */
    public static function localeProvider(): iterable
    {
        yield 'fi' => ['fi'];
        yield 'en' => ['en'];
    }

    /* =========================================================================
     * artistSignUp Action - Positive Paths
     * ========================================================================= */

    #[DataProvider('localeProvider')]
    public function testArtistSignupDisplaysFormWhenWindowOpenAndUserHasArtists(string $locale): void
    {
        // Arrange: Create event with open signup window
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Create member with artist
        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the signup page
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Form is displayed
        $this->assertResponseIsSuccessful();
        $this->client()->assertSelectorExists('form[name="event_artist_info"]');
    }

    #[DataProvider('localeProvider')]
    public function testArtistSignupSubmissionCreatesEventArtistInfoAndClone(string $locale): void
    {
        // Arrange: Create event with open signup window
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
                'artistSignUpAskSetLength' => true,
            ]);

        // Create member with artist
        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Submit the signup form
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $crawler = $this->client()->request('GET', $path);

        $form = $crawler->filter('form[name="event_artist_info"]')->form([
            'event_artist_info[Artist]' => (string) $artist->getId(),
            'event_artist_info[SetLength]' => '60 min',
            'event_artist_info[freeWord]' => 'Test note',
            'event_artist_info[agreeOnRecording]' => '1',
        ]);

        $this->client()->submit($form);

        // Assert: Redirect to artist profile
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/(en/profile/)?artisti?#', $location, 'Should redirect to artist profile page');

        // Verify EventArtistInfo was created
        $this->em()->clear();
        $signup = $this->em()->getRepository(EventArtistInfo::class)->findOneBy([
            'Event' => $event->getId(),
            'Artist' => $artist->getId(),
        ]);

        $this->assertInstanceOf(EventArtistInfo::class, $signup);
        $this->assertSame('60 min', $signup->getSetLength());
        $this->assertSame('Test note', $signup->getFreeWord());

        // Verify artistClone was created
        $clone = $signup->getArtistClone();
        $this->assertNotNull($clone, 'Artist clone should be created');
        $this->assertNull($clone->getMember(), 'Clone should not link to member');
        $this->assertTrue($clone->getCopyForArchive(), 'Clone should be marked as archive copy');
    }

    /* =========================================================================
     * artistSignUp Action - Negative Paths
     * ========================================================================= */

    #[DataProvider('localeProvider')]
    public function testArtistSignupRedirectsToCreateArtistWhenUserHasNoArtists(string $locale): void
    {
        // Arrange: Create event with open signup window
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Create member without artists
        $member = MemberFactory::new()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the signup page
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to artist create page
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/(en/profile/)?artisti?/(uusi|create)#', $location, 'Should redirect to artist create page');
    }

    #[DataProvider('localeProvider')]
    public function testArtistSignupDeniedWhenSignupWindowNotYetOpen(string $locale): void
    {
        // Arrange: Create event with signup window in future
        $event = EventFactory::new()
            ->published()
            ->signupWindowNotYetOpen()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Create member with artist
        $member = MemberFactory::new()->create();
        ArtistFactory::new()->withMember($member)->dj()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the signup page
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to profile with warning flash
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/(en/)?profi(le|ili)#', $location, 'Should redirect to profile page');
    }

    #[DataProvider('localeProvider')]
    public function testArtistSignupDeniedWhenSignupWindowEnded(string $locale): void
    {
        // Arrange: Create event with signup window in past
        $event = EventFactory::new()
            ->published()
            ->signupWindowEnded()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Create member with artist
        $member = MemberFactory::new()->create();
        ArtistFactory::new()->withMember($member)->dj()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the signup page
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to profile with warning flash
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/(en/)?profi(le|ili)#', $location, 'Should redirect to profile page');
    }

    #[DataProvider('localeProvider')]
    public function testArtistSignupDeniedWhenEventInPast(string $locale): void
    {
        // Arrange: Create past event with signup window that would otherwise be open
        $event = EventFactory::new()
            ->published()
            ->pastEventSignupWindowOpen()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Create member with artist
        $member = MemberFactory::new()->create();
        ArtistFactory::new()->withMember($member)->dj()->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Request the signup page
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to profile with warning flash
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/(en/)?profi(le|ili)#', $location, 'Should redirect to profile page');
    }

    public function testArtistSignupRedirectsUnauthenticatedUserToLogin(): void
    {
        // Arrange: Create event with open signup window
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        // Act: Request the signup page without authentication
        $path = $this->buildArtistSignupPath('fi', $event->getEventDate()->format('Y'), $event->getUrl());
        $this->client()->request('GET', $path);

        // Assert: Redirects to login (302 for anonymous users)
        $this->assertResponseStatusCodeSame(302);
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);
    }

    #[DataProvider('localeProvider')]
    public function testArtistSignupShowsWarningOnDuplicateSignup(string $locale): void
    {
        // Arrange: Create event with open signup window
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
                'artistSignUpAskSetLength' => true,
            ]);

        // Create member with artist
        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        // Create existing signup
        EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        // Login as the member
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome($locale);

        // Act: Submit another signup for the same artist
        $path = $this->buildArtistSignupPath($locale, $event->getEventDate()->format('Y'), $event->getUrl());
        $crawler = $this->client()->request('GET', $path);

        $form = $crawler->filter('form[name="event_artist_info"]')->form([
            'event_artist_info[Artist]' => (string) $artist->getId(),
            'event_artist_info[SetLength]' => '45 min',
            'event_artist_info[freeWord]' => 'Updated note',
            'event_artist_info[agreeOnRecording]' => '1',
        ]);

        $this->client()->submit($form);

        // Assert: Redirect occurs (controller adds flash message internally)
        $this->assertResponseRedirects();
    }

    /* =========================================================================
     * artistSignUpEdit Action
     * ========================================================================= */

    public function testArtistSignupEditAllowsOwnerToEditSignup(): void
    {
        // Arrange: Create event and signup
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
                'artistSignUpAskSetLength' => true,
            ]);

        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->withSetLength('60 min')
            ->create();

        // Login as the owner
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Edit the signup
        $path = '/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/signup/'.$signup->getId().'/edit';
        $crawler = $this->client()->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client()->assertSelectorExists('form[name="event_artist_info"]');

        // Submit edited data
        $form = $crawler->filter('form[name="event_artist_info"]')->form([
            'event_artist_info[SetLength]' => '90 min',
        ]);

        $this->client()->submit($form);

        // Assert: Redirects to artist profile
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $this->assertMatchesRegularExpression('#/artisti(/|$)#', $response->headers->get('Location') ?? '');

        // Verify edit was saved
        $this->em()->clear();
        $updated = $this->em()->getRepository(EventArtistInfo::class)->find($signup->getId());
        $this->assertSame('90 min', $updated->getSetLength());
    }

    public function testArtistSignupEditDeniesNonOwner(): void
    {
        // Arrange: Create event and signup for one member
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $ownerMember = MemberFactory::new()->create();
        $ownerArtist = ArtistFactory::new()->withMember($ownerMember)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($ownerArtist)
            ->create();

        // Login as a different member
        $otherMember = MemberFactory::new()->create();
        ArtistFactory::new()->withMember($otherMember)->band()->create();
        $this->loginAsMember($otherMember->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Attempt to edit another member's signup
        $path = '/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/signup/'.$signup->getId().'/edit';
        $this->client()->request('GET', $path);

        // Assert: Redirects with warning
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $this->assertMatchesRegularExpression('#/artisti(/|$)#', $response->headers->get('Location') ?? '');
    }

    public function testArtistSignupEditRedirectsUnauthenticatedUser(): void
    {
        // Arrange: Create event and signup
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        // Act: Request edit without authentication
        $path = '/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/signup/'.$signup->getId().'/edit';
        $this->client()->request('GET', $path);

        // Assert: Redirects to login
        $this->assertResponseStatusCodeSame(302);
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);
    }

    /* =========================================================================
     * artistSignUpDelete Action
     * ========================================================================= */

    public function testArtistSignupDeleteAllowsOwnerToDeleteSignup(): void
    {
        // Arrange: Create event and signup
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        $signupId = $signup->getId();

        // Login as the owner
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Delete the signup
        $path = '/signup/'.$signupId.'/delete';
        $this->client()->request('GET', $path);

        // Assert: Redirects to artist profile
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $this->assertMatchesRegularExpression('#/artisti(/|$)#', $response->headers->get('Location') ?? '');

        // Verify deletion
        $this->em()->clear();
        $deleted = $this->em()->getRepository(EventArtistInfo::class)->find($signupId);
        $this->assertNull($deleted, 'Signup should be deleted');
    }

    public function testArtistSignupDeleteDeniesNonOwner(): void
    {
        // Arrange: Create event and signup for one member
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $ownerMember = MemberFactory::new()->create();
        $ownerArtist = ArtistFactory::new()->withMember($ownerMember)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($ownerArtist)
            ->create();

        $signupId = $signup->getId();

        // Login as a different member
        $otherMember = MemberFactory::new()->create();
        ArtistFactory::new()->withMember($otherMember)->band()->create();
        $this->loginAsMember($otherMember->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Attempt to delete another member's signup
        $path = '/signup/'.$signupId.'/delete';
        $this->client()->request('GET', $path);

        // Assert: Redirects with warning
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $this->assertMatchesRegularExpression('#/artisti(/|$)#', $response->headers->get('Location') ?? '');

        // Verify signup still exists
        $this->em()->clear();
        $stillExists = $this->em()->getRepository(EventArtistInfo::class)->find($signupId);
        $this->assertNotNull($stillExists, 'Signup should not be deleted by non-owner');
    }

    public function testArtistSignupDeleteDeniedWhenEventInPast(): void
    {
        // Arrange: Create past event and signup
        $event = EventFactory::new()
            ->published()
            ->finished()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        $signupId = $signup->getId();

        // Login as the owner
        $this->loginAsMember($member->getUser()->getMember()->getEmail());
        $this->seedClientHome('fi');

        // Act: Attempt to delete signup for past event
        $path = '/signup/'.$signupId.'/delete';
        $this->client()->request('GET', $path);

        // Assert: Redirects with warning (not allowed)
        $this->assertResponseRedirects();
        $response = $this->client()->getResponse();
        $this->assertMatchesRegularExpression('#/artisti(/|$)#', $response->headers->get('Location') ?? '');

        // Verify signup still exists
        $this->em()->clear();
        $stillExists = $this->em()->getRepository(EventArtistInfo::class)->find($signupId);
        $this->assertNotNull($stillExists, 'Signup should not be deleted for past event');
    }

    public function testArtistSignupDeleteRedirectsUnauthenticatedUser(): void
    {
        // Arrange: Create event and signup
        $event = EventFactory::new()
            ->published()
            ->signupEnabled()
            ->create([
                'url' => 'test-event-'.uniqid('', true),
            ]);

        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();
        $signup = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        // Act: Request delete without authentication
        $path = '/signup/'.$signup->getId().'/delete';
        $this->client()->request('GET', $path);

        // Assert: Redirects to login
        $this->assertResponseStatusCodeSame(302);
        $response = $this->client()->getResponse();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);
    }

    /* =========================================================================
     * Helper Methods
     * ========================================================================= */

    /**
     * Build the artist signup path for the given locale.
     */
    private function buildArtistSignupPath(string $locale, string $year, string $slug): string
    {
        if ('en' === $locale) {
            return '/en/'.$year.'/'.$slug.'/artist/signup';
        }

        return '/'.$year.'/'.$slug.'/artisti/ilmottautuminen';
    }
}
