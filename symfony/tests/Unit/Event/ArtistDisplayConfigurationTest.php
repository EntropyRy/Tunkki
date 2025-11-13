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

    public function testNormalizeTypeMapsLiveToDj(): void
    {
        $config = new ArtistDisplayConfiguration();
        $config->setBioShowGenre('LIVE', false);

        self::assertFalse($config->shouldBioShowGenre('live'));
        self::assertFalse($config->shouldBioShowGenre('DJ'));
    }
}
