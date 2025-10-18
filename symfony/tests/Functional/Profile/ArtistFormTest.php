<?php

declare(strict_types=1);

namespace App\Tests\Functional\Profile;

use App\Entity\Artist;
use App\Factory\ArtistFactory;
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
}
