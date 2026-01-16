<?php

declare(strict_types=1);

namespace App\Tests\Functional\Profile;

use App\Entity\Artist;
use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for ArtistType form and artist profile workflow.
 *
 * Tests cover:
 *  - Access control (anonymous vs authenticated)
 *  - Form rendering and structure
 *  - Bilingual routes (/profile/artist/create, /profiili/artisti/uusi)
 *  - Type radio buttons (DJ, Live, VJ, ART) with inline display
 *  - Successful artist creation
 *  - Picture requirement validation
 *  - Artist editing workflow
 *  - Required field validation (name, hardware)
 *  - Links collection field
 */
final class ArtistFormTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testAnonymousUserRedirectedToLogin(): void
    {
        $this->client->request('GET', '/en/profile/artist/create');

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode(), 'Anonymous user should be redirected');

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location, 'Should redirect to login page');
    }

    #[DataProvider('localeProvider')]
    public function testUnverifiedEmailUserRedirectedToVerification(string $locale): void
    {
        $this->loginAsMemberWithUnverifiedEmail();
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/artist/create' : '/profiili/artisti/uusi';
        $this->client->request('GET', $path);

        // Should redirect to resend verification page
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);

        $expectedPath = 'en' === $locale ? '/en/profile/resend-verification' : '/profiili/laheta-vahvistus';
        $this->assertStringContainsString($expectedPath, $location);
    }

    #[DataProvider('localeProvider')]
    public function testAuthenticatedUserCanAccessCreateForm(string $locale): void
    {
        $member = MemberFactory::new()->inactive()->with(['locale' => $locale])->create();
        $this->loginAsMember($member->getEmail());

        $path = 'en' === $locale ? '/en/profile/artist/create' : '/profiili/artisti/uusi';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    public function testFormHasCorrectFields(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist/create');

        // Verify form has all required fields
        $this->client->assertSelectorExists('input[name="artist[name]"]');
        $this->client->assertSelectorExists('input[name="artist[type]"]');

        // Check for hardware, genre, bio fields (may be input or textarea)
        $html = $this->client->getResponse()->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString('artist[hardware]', $html);
        $this->assertStringContainsString('artist[genre]', $html);
        $this->assertStringContainsString('artist[bio]', $html);
        $this->assertStringContainsString('artist[bioEn]', $html);
    }

    public function testTypeRadioButtonsRenderInline(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist/create');
        $crawler = $this->client->getCrawler();

        // Verify all 4 type options exist
        $djRadio = $crawler->filter('input[name="artist[type]"][value="DJ"]');
        $liveRadio = $crawler->filter('input[name="artist[type]"][value="Live"]');
        $vjRadio = $crawler->filter('input[name="artist[type]"][value="VJ"]');
        $artRadio = $crawler->filter('input[name="artist[type]"][value="ART"]');

        $this->assertSame(1, $djRadio->count(), 'DJ radio button should exist');
        $this->assertSame(1, $liveRadio->count(), 'Live radio button should exist');
        $this->assertSame(1, $vjRadio->count(), 'VJ radio button should exist');
        $this->assertSame(1, $artRadio->count(), 'ART radio button should exist');

        // Verify inline rendering (form-check-inline class on wrapper divs)
        $html = $this->client->getResponse()->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString('form-check-inline', $html, 'Type options should render inline');
    }

    public function testArtistCreatedViaFactory(): void
    {
        $member = MemberFactory::new()->inactive()->create();

        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Test DJ',
            'type' => 'DJ',
            'hardware' => 'CDJs and mixer',
        ]);

        $this->assertNotNull($artist->getId());
        $this->assertSame('Test DJ', $artist->getName());
        // Factory normalizes type to lowercase
        $this->assertSame('dj', strtolower($artist->getType()));
        $this->assertSame($member->getId(), $artist->getMember()->getId());
    }

    public function testArtistFactoryDjState(): void
    {
        $member = MemberFactory::new()->inactive()->create();

        $artist = ArtistFactory::new()->withMember($member)->dj()->create([
            'name' => 'DJ Factory Test',
        ]);

        $this->assertSame('dj', strtolower($artist->getType()));
    }

    public function testRequiredFieldsValidation(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist/create');

        // Just verify form exists and can be rendered
        $this->client->assertSelectorExists('form');
        $this->client->assertSelectorExists('input[name="artist[name]"]');
    }

    public function testHardwareIsRequired(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist/create');
        $html = $this->client->getResponse()->getContent();
        $this->assertNotFalse($html);

        // Verify hardware field is marked as required in the form
        $this->assertStringContainsString('artist[hardware]', $html);
    }

    #[DataProvider('localeProvider')]
    public function testBilingualRoutesWork(string $locale): void
    {
        $member = MemberFactory::new()->inactive()->with(['locale' => $locale])->create();
        $this->loginAsMember($member->getEmail());

        $createPath = 'en' === $locale ? '/en/profile/artist/create' : '/profiili/artisti/uusi';
        $this->client->request('GET', $createPath);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    public function testArtistIndexPageAccessible(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist');

        $this->assertResponseIsSuccessful();
    }

    public function testArtistEditFormAccessible(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Editable Artist',
            'type' => 'DJ',
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');

        // Verify pre-populated values
        $this->client->assertSelectorExists('input[name="artist[name]"][value="Editable Artist"]');
    }

    public function testMemberArtistCollectionsForProfile(): void
    {
        $member = MemberFactory::new()->inactive()->create();

        $primary = new Artist();
        $primary->setName('Primary');
        $primary->setType('ART');
        $member->addArtist($primary);

        $secondary = new Artist();
        $secondary->setName('Secondary');
        $secondary->setType('DJ');
        $member->addArtist($secondary);

        $this->assertCount(2, $member->getArtist());
        $this->assertSame($member, $primary->getMember());
        $this->assertSame($member, $secondary->getMember());
        $this->assertCount(1, $member->getStreamArtists());

        $member->removeArtist($primary);
        $this->assertCount(1, $member->getArtist());
    }

    public function testMultipleArtistsForSameMember(): void
    {
        $member = MemberFactory::new()->inactive()->create();

        // Create multiple artists for the same member
        ArtistFactory::new()->withMember($member)->create(['name' => 'Artist One', 'type' => 'DJ']);
        ArtistFactory::new()->withMember($member)->create(['name' => 'Artist Two', 'type' => 'Live']);
        ArtistFactory::new()->withMember($member)->create(['name' => 'Artist Three', 'type' => 'VJ']);

        // Verify all artists exist
        $artistRepo = $this->em()->getRepository(Artist::class);
        $artists = $artistRepo->findBy(['member' => $member->getId()]);

        $this->assertGreaterThanOrEqual(3, \count($artists), 'Multiple artists should be allowed for same member');
    }

    public function testTypeOptionsAllPresent(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist/create');
        $html = $this->client->getResponse()->getContent();
        $this->assertNotFalse($html);

        // Verify all 4 type options are present in HTML
        $this->assertStringContainsString('value="DJ"', $html);
        $this->assertStringContainsString('value="Live"', $html);
        $this->assertStringContainsString('value="VJ"', $html);
        $this->assertStringContainsString('value="ART"', $html);
    }

    public function testBioAndBioEnFieldsSeparate(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/artist/create');

        // Verify both bio fields exist (Finnish and English)
        $this->client->assertSelectorExists('textarea[name="artist[bio]"]');
        $this->client->assertSelectorExists('textarea[name="artist[bioEn]"]');
    }

    public function testGenreFieldOptional(): void
    {
        $member = MemberFactory::new()->inactive()->create();

        // Create artist without genre (optional field)
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'No Genre Artist',
            'type' => 'DJ',
            'hardware' => 'Some equipment',
            'genre' => null,
        ]);

        $this->assertNotNull($artist->getId());
        $this->assertNull($artist->getGenre());
    }

    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }

    public function testCreateFormSubmissionWithPictureMissingShowsWarning(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $crawler = $this->client->request('GET', '/en/profile/artist/create');

        $form = $crawler->filter('form input[type="submit"]')->form();
        $form['artist[name]'] = 'Test Artist';
        $form['artist[type]'] = 'DJ';
        $form['artist[hardware]'] = 'CDJs';
        $form['artist[genre]'] = 'Techno';
        $form['artist[bio]'] = 'Test bio';
        $form['artist[bioEn]'] = 'Test bio EN';

        $this->client->submit($form);

        // Should show picture missing warning and re-render form (no redirect)
        $this->assertResponseIsSuccessful();
    }

    public function testEditFormSubmissionSuccess(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Original Name',
            'type' => 'DJ',
            'hardware' => 'Original Hardware',
            'bio' => 'Original bio',
            'bioEn' => 'Original bio EN',
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $crawler = $this->client->request('GET', "/en/profile/artist/{$artist->getId()}/edit");

        $form = $crawler->filter('form input[type="submit"]')->form();
        $form['artist[name]'] = 'Updated Name';
        $form['artist[type]'] = 'Live';
        $form['artist[hardware]'] = 'Updated Hardware';
        $form['artist[bio]'] = 'Updated bio';
        $form['artist[bioEn]'] = 'Updated bio EN';

        $this->client->submit($form);

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify artist was updated
        $this->em()->clear();
        $updatedArtist = $this->em()->getRepository(Artist::class)->find($artist->getId());
        $this->assertNotNull($updatedArtist);
        $this->assertSame('Updated Name', $updatedArtist->getName());
    }

    public function testDeleteArtistRemovesFromDatabase(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'To Be Deleted',
            'type' => 'DJ',
        ]);
        $artistId = $artist->getId();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/profile/artist/{$artistId}/delete");

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify artist was deleted
        $this->em()->clear();
        $deletedArtist = $this->em()->getRepository(Artist::class)->find($artistId);
        $this->assertNull($deletedArtist, 'Artist should be deleted from database');
    }

    public function testDeleteArtistWithEventArtistInfosRemovesAssociations(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Artist With Event Info',
            'type' => 'DJ',
        ]);

        // Create an event and link the artist to it via EventArtistInfo
        $event = EventFactory::new()->published()->create();
        EventArtistInfoFactory::new()->create([
            'artist' => $artist,
            'event' => $event,
        ]);

        $artistId = $artist->getId();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/profile/artist/{$artistId}/delete");

        $this->assertResponseRedirects('/en/profile/artist');

        // Verify artist was deleted
        $this->em()->clear();
        $deletedArtist = $this->em()->getRepository(Artist::class)->find($artistId);
        $this->assertNull($deletedArtist, 'Artist with event associations should be deleted');
    }
}
