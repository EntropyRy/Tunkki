<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class ArtistDisplayConfiguration
{
    private const array DEFAULTS = [
        'DJ' => [
            'timetable' => [
                'include_page_links' => false,
                'include_genre' => true,
                'include_time' => true,
            ],
            'bio' => [
                'show_stage' => true,
                'show_picture' => true,
                'show_time' => true,
                'show_genre' => true,
            ],
        ],
        'VJ' => [
            'timetable' => [
                'include_page_links' => false,
                'include_genre' => true,
                'include_time' => true,
            ],
            'bio' => [
                'show_stage' => true,
                'show_picture' => true,
                'show_time' => true,
                'show_genre' => true,
            ],
        ],
        'ART' => [
            'timetable' => [
                'include_page_links' => false,
                'include_genre' => true,
                'include_time' => true,
            ],
            'bio' => [
                'show_stage' => true,
                'show_picture' => true,
                'show_time' => false,
                'show_genre' => true,
            ],
        ],
    ];

    #[ORM\Column(name: 'artist_display_config', type: Types::JSON, nullable: true)]
    private ?array $config = null;

    public function __construct(?array $config = null)
    {
        $this->config = $this->normalizeConfig($config);
    }

    public static function getDefaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->mergeConfig($this->config);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function replace(?array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTimetableConfig(string $type): array
    {
        $normalized = $this->normalizeType($type);

        return $this->getConfig()[$normalized]['timetable'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBioConfig(string $type): array
    {
        $normalized = $this->normalizeType($type);

        return $this->getConfig()[$normalized]['bio'];
    }

    public function shouldTimetableIncludePageLinks(string $type): bool
    {
        return $this->getFlag($type, 'timetable', 'include_page_links');
    }

    public function shouldTimetableShowGenre(string $type): bool
    {
        return $this->getFlag($type, 'timetable', 'include_genre');
    }

    public function shouldTimetableShowTime(string $type): bool
    {
        return $this->getFlag($type, 'timetable', 'include_time');
    }

    public function shouldBioShowStage(string $type): bool
    {
        return $this->getFlag($type, 'bio', 'show_stage');
    }

    public function shouldBioShowPicture(string $type): bool
    {
        return $this->getFlag($type, 'bio', 'show_picture');
    }

    public function shouldBioShowTime(string $type): bool
    {
        return $this->getFlag($type, 'bio', 'show_time');
    }

    public function shouldBioShowGenre(string $type): bool
    {
        return $this->getFlag($type, 'bio', 'show_genre');
    }

    public function setTimetableIncludePageLinks(string $type, bool $value): void
    {
        $this->setFlag($type, 'timetable', 'include_page_links', $value);
    }

    public function setTimetableShowGenre(string $type, bool $value): void
    {
        $this->setFlag($type, 'timetable', 'include_genre', $value);
    }

    public function setTimetableShowTime(string $type, bool $value): void
    {
        $this->setFlag($type, 'timetable', 'include_time', $value);
    }

    public function setBioShowStage(string $type, bool $value): void
    {
        $this->setFlag($type, 'bio', 'show_stage', $value);
    }

    public function setBioShowPicture(string $type, bool $value): void
    {
        $this->setFlag($type, 'bio', 'show_picture', $value);
    }

    public function setBioShowTime(string $type, bool $value): void
    {
        $this->setFlag($type, 'bio', 'show_time', $value);
    }

    public function setBioShowGenre(string $type, bool $value): void
    {
        $this->setFlag($type, 'bio', 'show_genre', $value);
    }

    public function getDjTimetableIncludePageLinks(): bool
    {
        return $this->shouldTimetableIncludePageLinks('DJ');
    }

    public function setDjTimetableIncludePageLinks(bool $value): void
    {
        $this->setTimetableIncludePageLinks('DJ', $value);
    }

    public function getDjTimetableShowGenre(): bool
    {
        return $this->shouldTimetableShowGenre('DJ');
    }

    public function setDjTimetableShowGenre(bool $value): void
    {
        $this->setTimetableShowGenre('DJ', $value);
    }

    public function getDjTimetableShowTime(): bool
    {
        return $this->shouldTimetableShowTime('DJ');
    }

    public function setDjTimetableShowTime(bool $value): void
    {
        $this->setTimetableShowTime('DJ', $value);
    }

    public function getDjBioShowStage(): bool
    {
        return $this->shouldBioShowStage('DJ');
    }

    public function setDjBioShowStage(bool $value): void
    {
        $this->setBioShowStage('DJ', $value);
    }

    public function getDjBioShowPicture(): bool
    {
        return $this->shouldBioShowPicture('DJ');
    }

    public function setDjBioShowPicture(bool $value): void
    {
        $this->setBioShowPicture('DJ', $value);
    }

    public function getDjBioShowTime(): bool
    {
        return $this->shouldBioShowTime('DJ');
    }

    public function setDjBioShowTime(bool $value): void
    {
        $this->setBioShowTime('DJ', $value);
    }

    public function getDjBioShowGenre(): bool
    {
        return $this->shouldBioShowGenre('DJ');
    }

    public function setDjBioShowGenre(bool $value): void
    {
        $this->setBioShowGenre('DJ', $value);
    }

    public function getVjTimetableIncludePageLinks(): bool
    {
        return $this->shouldTimetableIncludePageLinks('VJ');
    }

    public function setVjTimetableIncludePageLinks(bool $value): void
    {
        $this->setTimetableIncludePageLinks('VJ', $value);
    }

    public function getVjTimetableShowGenre(): bool
    {
        return $this->shouldTimetableShowGenre('VJ');
    }

    public function setVjTimetableShowGenre(bool $value): void
    {
        $this->setTimetableShowGenre('VJ', $value);
    }

    public function getVjTimetableShowTime(): bool
    {
        return $this->shouldTimetableShowTime('VJ');
    }

    public function setVjTimetableShowTime(bool $value): void
    {
        $this->setTimetableShowTime('VJ', $value);
    }

    public function getVjBioShowStage(): bool
    {
        return $this->shouldBioShowStage('VJ');
    }

    public function setVjBioShowStage(bool $value): void
    {
        $this->setBioShowStage('VJ', $value);
    }

    public function getVjBioShowPicture(): bool
    {
        return $this->shouldBioShowPicture('VJ');
    }

    public function setVjBioShowPicture(bool $value): void
    {
        $this->setBioShowPicture('VJ', $value);
    }

    public function getVjBioShowTime(): bool
    {
        return $this->shouldBioShowTime('VJ');
    }

    public function setVjBioShowTime(bool $value): void
    {
        $this->setBioShowTime('VJ', $value);
    }

    public function getVjBioShowGenre(): bool
    {
        return $this->shouldBioShowGenre('VJ');
    }

    public function setVjBioShowGenre(bool $value): void
    {
        $this->setBioShowGenre('VJ', $value);
    }

    public function getArtBioShowStage(): bool
    {
        return $this->shouldBioShowStage('ART');
    }

    public function setArtBioShowStage(bool $value): void
    {
        $this->setBioShowStage('ART', $value);
    }

    public function getArtBioShowPicture(): bool
    {
        return $this->shouldBioShowPicture('ART');
    }

    public function setArtBioShowPicture(bool $value): void
    {
        $this->setBioShowPicture('ART', $value);
    }

    public function getArtBioShowTime(): bool
    {
        return $this->shouldBioShowTime('ART');
    }

    public function setArtBioShowTime(bool $value): void
    {
        $this->setBioShowTime('ART', $value);
    }

    public function getArtBioShowGenre(): bool
    {
        return $this->shouldBioShowGenre('ART');
    }

    public function setArtBioShowGenre(bool $value): void
    {
        $this->setBioShowGenre('ART', $value);
    }

    private function mergeConfig(?array $config): array
    {
        return array_replace_recursive(
            self::DEFAULTS,
            $this->normalizeConfig($config) ?? [],
        );
    }

    private function setFlag(
        string $type,
        string $group,
        string $flag,
        bool $value,
    ): void {
        $normalized = $this->normalizeType($type);
        $config = $this->config ?? [];
        $config[$normalized][$group][$flag] = $value;

        $this->config = $config;
    }

    private function getFlag(
        string $type,
        string $group,
        string $flag,
    ): bool {
        $normalized = $this->normalizeType($type);
        $config = $this->getConfig();

        if (
            \array_key_exists($normalized, $config)
            && \array_key_exists($group, $config[$normalized])
            && \array_key_exists($flag, $config[$normalized][$group])
        ) {
            return (bool) $config[$normalized][$group][$flag];
        }

        return false;
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtoupper($type);

        return match ($normalized) {
            'LIVE' => 'DJ',
            'VJ', 'ART', 'DJ' => $normalized,
            default => 'DJ',
        };
    }

    /**
     * @param array<string, mixed>|null $config
     *
     * @return array<string, mixed>|null
     */
    private function normalizeConfig(?array $config): ?array
    {
        if (null === $config) {
            return null;
        }

        $normalized = [];
        foreach ($config as $type => $groups) {
            if (!\is_array($groups)) {
                continue;
            }

            $normalizedType = $this->normalizeType((string) $type);
            foreach ($groups as $group => $flags) {
                if (!\is_array($flags)) {
                    continue;
                }

                $normalized[$normalizedType][$group] ??= [];
                foreach ($flags as $flag => $value) {
                    $normalized[$normalizedType][$group][$flag] = (bool) $value;
                }
            }
        }

        return $normalized;
    }
}
