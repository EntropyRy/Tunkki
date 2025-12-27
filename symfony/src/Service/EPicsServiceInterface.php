<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Interface for ePics photo gallery service.
 *
 * Provides methods for:
 * - Fetching random photos for display
 * - User account management
 */
interface EPicsServiceInterface
{
    /**
     * Fetch a random photo from the ePics gallery.
     *
     * @return array{url: string, taken: string|null}|null Photo data with URL and timestamp, or null on failure
     */
    public function getRandomPhoto(): ?array;

    /**
     * Create or update an ePics user account with the given password.
     *
     * @param string $username Username (typically email or member username)
     * @param string $password Plain text password
     *
     * @return bool True on success, false on failure
     */
    public function createOrUpdateUserPassword(string $username, string $password): bool;
}
