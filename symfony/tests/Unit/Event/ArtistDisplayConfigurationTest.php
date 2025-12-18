<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event;

use App\Entity\ArtistDisplayConfiguration;
use PHPUnit\Framework\TestCase;

final class ArtistDisplayConfigurationTest extends TestCase
{
    public function testDefaultsApplied(): void
    {
        $config = new ArtistDisplayConfiguration();

        self::assertTrue($config->shouldTimetableShowTime('DJ'));
        self::assertTrue($config->shouldTimetableShowGenre('DJ'));
        self::assertFalse($config->shouldTimetableIncludePageLinks('DJ'));

        self::assertTrue($config->shouldBioShowStage('VJ'));
        self::assertTrue($config->shouldBioShowPicture('VJ'));
        self::assertTrue($config->shouldBioShowGenre('VJ'));
        self::assertTrue($config->shouldBioShowTime('VJ'));

        // ART defaults differ for show_time.
        self::assertFalse($config->shouldBioShowTime('ART'));
    }

    public function testGetDefaultsExposesExpectedKeys(): void
    {
        $defaults = ArtistDisplayConfiguration::getDefaults();

        self::assertArrayHasKey('DJ', $defaults);
        self::assertArrayHasKey('VJ', $defaults);
        self::assertArrayHasKey('ART', $defaults);

        self::assertArrayHasKey('timetable', $defaults['DJ']);
        self::assertArrayHasKey('bio', $defaults['DJ']);
    }

    public function testDefaultsViaGetConfigAreMergedAndStructured(): void
    {
        $config = new ArtistDisplayConfiguration();
        $full = $config->getConfig();

        self::assertArrayHasKey('DJ', $full);
        self::assertArrayHasKey('VJ', $full);
        self::assertArrayHasKey('ART', $full);

        self::assertArrayHasKey('timetable', $full['DJ']);
        self::assertArrayHasKey('bio', $full['DJ']);

        self::assertArrayHasKey('include_page_links', $full['DJ']['timetable']);
        self::assertArrayHasKey('include_genre', $full['DJ']['timetable']);
        self::assertArrayHasKey('include_time', $full['DJ']['timetable']);

        self::assertArrayHasKey('show_stage', $full['DJ']['bio']);
        self::assertArrayHasKey('show_picture', $full['DJ']['bio']);
        self::assertArrayHasKey('show_time', $full['DJ']['bio']);
        self::assertArrayHasKey('show_genre', $full['DJ']['bio']);
    }

    public function testPrivateGetFlagReturnsFalseWhenGroupOrFlagMissing(): void
    {
        $config = new ArtistDisplayConfiguration();

        $ref = new \ReflectionClass(ArtistDisplayConfiguration::class);
        $method = $ref->getMethod('getFlag');
        $method->setAccessible(true);

        // Use a non-existent group/flag to hit the defensive false-return branch.
        $result = $method->invoke($config, 'DJ', 'nonexistent_group', 'nonexistent_flag');

        self::assertFalse($result);
    }

    public function testCustomConfigurationOverridesDefaults(): void
    {
        $config = new ArtistDisplayConfiguration([
            'dj' => [
                'bio' => [
                    'show_stage' => false,
                    'show_genre' => false,
                ],
                'timetable' => [
                    'include_time' => false,
                    'include_genre' => false,
                ],
            ],
        ]);

        self::assertFalse($config->shouldBioShowStage('DJ'));
        self::assertFalse($config->shouldBioShowGenre('DJ'));
        self::assertFalse($config->shouldTimetableShowTime('DJ'));
        self::assertFalse($config->shouldTimetableShowGenre('DJ'));

        // Untouched type falls back to defaults.
        self::assertTrue($config->shouldBioShowStage('VJ'));
    }

    public function testGetTimetableConfigAndGetBioConfigReturnMergedValues(): void
    {
        $config = new ArtistDisplayConfiguration([
            'DJ' => [
                'timetable' => [
                    'include_page_links' => true,
                ],
            ],
        ]);

        $timetable = $config->getTimetableConfig('dj');
        self::assertTrue((bool) $timetable['include_page_links']);
        self::assertTrue((bool) $timetable['include_genre']);
        self::assertTrue((bool) $timetable['include_time']);

        $bio = $config->getBioConfig('DJ');
        self::assertTrue((bool) $bio['show_stage']);
        self::assertTrue((bool) $bio['show_picture']);
        self::assertTrue((bool) $bio['show_time']);
        self::assertTrue((bool) $bio['show_genre']);
    }

    public function testReplaceNullResetsToDefaultsViaMerge(): void
    {
        $config = new ArtistDisplayConfiguration([
            'DJ' => [
                'timetable' => [
                    'include_page_links' => true,
                ],
            ],
        ]);

        self::assertTrue($config->shouldTimetableIncludePageLinks('DJ'));

        $config->replace(null);

        self::assertFalse($config->shouldTimetableIncludePageLinks('DJ'));
        self::assertTrue($config->shouldTimetableShowGenre('DJ'));
        self::assertTrue($config->shouldTimetableShowTime('DJ'));
    }

    public function testSettersMutateConfiguration(): void
    {
        $config = new ArtistDisplayConfiguration();

        $config->setTimetableIncludePageLinks('DJ', true);
        $config->setTimetableShowGenre('VJ', false);
        $config->setBioShowStage('ART', false);
        $config->setBioShowPicture('ART', false);

        self::assertTrue($config->shouldTimetableIncludePageLinks('DJ'));
        self::assertFalse($config->shouldTimetableShowGenre('VJ'));
        self::assertFalse($config->shouldBioShowStage('ART'));
        self::assertFalse($config->shouldBioShowPicture('ART'));
    }

    public function testConvenienceGettersAndSettersDelegateToGenericMethods(): void
    {
        $config = new ArtistDisplayConfiguration();

        // DJ timetable
        self::assertFalse($config->getDjTimetableIncludePageLinks());
        $config->setDjTimetableIncludePageLinks(true);
        self::assertTrue($config->getDjTimetableIncludePageLinks());

        self::assertTrue($config->getDjTimetableShowGenre());
        $config->setDjTimetableShowGenre(false);
        self::assertFalse($config->getDjTimetableShowGenre());

        self::assertTrue($config->getDjTimetableShowTime());
        $config->setDjTimetableShowTime(false);
        self::assertFalse($config->getDjTimetableShowTime());

        // DJ bio (these were previously uncovered)
        self::assertTrue($config->getDjBioShowStage());
        $config->setDjBioShowStage(false);
        self::assertFalse($config->getDjBioShowStage());

        self::assertTrue($config->getDjBioShowPicture());
        $config->setDjBioShowPicture(false);
        self::assertFalse($config->getDjBioShowPicture());

        self::assertTrue($config->getDjBioShowTime());
        $config->setDjBioShowTime(false);
        self::assertFalse($config->getDjBioShowTime());

        self::assertTrue($config->getDjBioShowGenre());
        $config->setDjBioShowGenre(false);
        self::assertFalse($config->getDjBioShowGenre());

        // VJ timetable
        self::assertFalse($config->getVjTimetableIncludePageLinks());
        $config->setVjTimetableIncludePageLinks(true);
        self::assertTrue($config->getVjTimetableIncludePageLinks());

        self::assertTrue($config->getVjTimetableShowGenre());
        $config->setVjTimetableShowGenre(false);
        self::assertFalse($config->getVjTimetableShowGenre());

        self::assertTrue($config->getVjTimetableShowTime());
        $config->setVjTimetableShowTime(false);
        self::assertFalse($config->getVjTimetableShowTime());

        // VJ bio
        self::assertTrue($config->getVjBioShowStage());
        $config->setVjBioShowStage(false);
        self::assertFalse($config->getVjBioShowStage());

        self::assertTrue($config->getVjBioShowPicture());
        $config->setVjBioShowPicture(false);
        self::assertFalse($config->getVjBioShowPicture());

        self::assertTrue($config->getVjBioShowTime());
        $config->setVjBioShowTime(false);
        self::assertFalse($config->getVjBioShowTime());

        self::assertTrue($config->getVjBioShowGenre());
        $config->setVjBioShowGenre(false);
        self::assertFalse($config->getVjBioShowGenre());

        // ART bio
        self::assertTrue($config->getArtBioShowStage());
        $config->setArtBioShowStage(false);
        self::assertFalse($config->getArtBioShowStage());

        self::assertTrue($config->getArtBioShowPicture());
        $config->setArtBioShowPicture(false);
        self::assertFalse($config->getArtBioShowPicture());

        // ART default differs for show_time
        self::assertFalse($config->getArtBioShowTime());
        $config->setArtBioShowTime(true);
        self::assertTrue($config->getArtBioShowTime());

        self::assertTrue($config->getArtBioShowGenre());
        $config->setArtBioShowGenre(false);
        self::assertFalse($config->getArtBioShowGenre());
    }

    public function testNormalizeTypeMapsLiveToDj(): void
    {
        $config = new ArtistDisplayConfiguration();
        $config->setBioShowGenre('LIVE', false);

        self::assertFalse($config->shouldBioShowGenre('live'));
        self::assertFalse($config->shouldBioShowGenre('DJ'));
    }

    public function testNormalizeTypeFallsBackToDjForUnknownTypes(): void
    {
        $config = new ArtistDisplayConfiguration();

        $config->setTimetableIncludePageLinks('UNKNOWN_TYPE', true);

        self::assertTrue($config->shouldTimetableIncludePageLinks('DJ'));
        self::assertTrue($config->shouldTimetableIncludePageLinks('unknown_type'));
    }

    public function testNormalizeConfigSkipsNonArrayGroupsAndFlagsAndCastsToBool(): void
    {
        $config = new ArtistDisplayConfiguration([
            'DJ' => 'not-an-array',
            'VJ' => [
                'bio' => 'not-an-array',
                'timetable' => [
                    'include_page_links' => 1,
                    'include_genre' => 0,
                    'include_time' => '0',
                ],
            ],
        ]);

        // DJ untouched -> defaults
        self::assertFalse($config->shouldTimetableIncludePageLinks('DJ'));

        // VJ timetable values should be cast to bool
        self::assertTrue($config->shouldTimetableIncludePageLinks('VJ'));
        self::assertFalse($config->shouldTimetableShowGenre('VJ'));
        self::assertFalse($config->shouldTimetableShowTime('VJ'));
    }
}
