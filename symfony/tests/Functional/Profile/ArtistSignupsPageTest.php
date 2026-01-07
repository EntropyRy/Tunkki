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

final class ArtistSignupsPageTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    #[DataProvider('localeProvider')]
    public function testSignupsPageShowsSectionsAndDetails(
        string $locale,
        string $pathTemplate,
        string $upcomingHeading,
        string $pastHeading,
        string $setLengthLabel,
        string $wishLabel,
        string $freeWordLabel,
    ): void {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Signup Artist',
        ]);

        $upcomingEvent = EventFactory::new()->published()->signupEnabled()->create([
            'name' => 'Upcoming Signup Event',
            'eventDate' => new \DateTimeImmutable('+10 days'),
        ]);
        EventArtistInfoFactory::new()
            ->forEvent($upcomingEvent)
            ->forArtist($artist)
            ->withSetLength('45 min')
            ->withStartTimeWish('23:15')
            ->withNote('Please place after midnight.')
            ->create();

        $pastEvent = EventFactory::new()->published()->past()->create([
            'name' => 'Past Signup Event',
        ]);
        EventArtistInfoFactory::new()
            ->forEvent($pastEvent)
            ->forArtist($artist)
            ->create();

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $crawler = $this->client->request(
            'GET',
            \sprintf($pathTemplate, $artist->getId()),
        );

        $this->assertResponseIsSuccessful();

        $headings = $crawler->filter('.list-group-item.active');
        $this->assertSame(2, $headings->count());
        $this->assertSame($upcomingHeading, trim($headings->eq(0)->text()));
        $this->assertSame($pastHeading, trim($headings->eq(1)->text()));

        $detailsSelector = '.list-group-item:not(.active)';
        $this->client->assertSelectorTextContains($detailsSelector, $setLengthLabel);
        $this->client->assertSelectorTextContains($detailsSelector, $wishLabel);
        $this->client->assertSelectorTextContains($detailsSelector, $freeWordLabel);
        $this->client->assertSelectorTextContains($detailsSelector, '45 min');
        $this->client->assertSelectorTextContains($detailsSelector, '23:15');
        $this->client->assertSelectorTextContains($detailsSelector, 'Please place after midnight.');
    }

    #[DataProvider('localeAccessProvider')]
    public function testSignupsPageDeniedForOtherMember(
        string $locale,
        string $pathTemplate,
    ): void {
        $owner = MemberFactory::new()->active()->create();
        $other = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()->withMember($owner)->create();

        $this->loginAsMember($other->getEmail());
        $this->seedClientHome($locale);

        $this->client->request('GET', \sprintf($pathTemplate, $artist->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    public static function localeProvider(): array
    {
        return [
            [
                'fi',
                '/profiili/artisti/%d/ilmoittautumiset',
                'Tuleva tapahtuma',
                'Mennyt tapahtuma',
                'Toive setin pituudesta',
                'Toive ajankohdasta',
                'Vapaa sana',
            ],
            [
                'en',
                '/en/profile/artist/%d/signups',
                'Upcoming event',
                'Past event',
                'Preferred length of the set',
                'At what time would you like to perform?',
                'Additional notes',
            ],
        ];
    }

    public static function localeAccessProvider(): array
    {
        return [
            ['fi', '/profiili/artisti/%d/ilmoittautumiset'],
            ['en', '/en/profile/artist/%d/signups'],
        ];
    }
}
