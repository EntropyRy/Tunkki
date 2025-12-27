<?php

declare(strict_types=1);

namespace App\Tests\Support\EPics;

use App\Service\EPicsServiceInterface;

/**
 * Fake EPics Service for testing.
 *
 * Provides configurable responses without making real HTTP calls
 * to the external Lychee photo gallery API.
 */
final class FakeEPicsService implements EPicsServiceInterface
{
    /** @var array{url: string, taken: string|null}|null */
    private ?array $photoData = null;

    private bool $hasPhoto = true;

    public function __construct()
    {
        // Set default photo data
        $this->photoData = [
            'url' => 'https://epics.test.local/uploads/medium/test-photo.jpg',
            'taken' => '2025-01-01T12:00:00+00:00',
        ];
    }

    /**
     * Configure whether a photo should be returned.
     */
    public function setHasPhoto(bool $hasPhoto): void
    {
        $this->hasPhoto = $hasPhoto;
    }

    /**
     * Set specific photo data to return.
     *
     * @param array{url: string, taken: string|null}|null $photoData
     */
    public function setPhotoData(?array $photoData): void
    {
        $this->photoData = $photoData;
    }

    public function getRandomPhoto(): ?array
    {
        if (!$this->hasPhoto) {
            return null;
        }

        return $this->photoData;
    }

    public function createOrUpdateUserPassword(string $username, string $password): bool
    {
        // Always succeed in tests
        return true;
    }
}
