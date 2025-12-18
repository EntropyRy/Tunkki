<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Represents a single email recipient with their locale preference.
 */
final readonly class EmailRecipient
{
    public function __construct(
        public string $email,
        public string $locale = 'fi',
        public ?int $memberId = null,
    ) {
    }

    /**
     * Get unique key for deduplication (by email address).
     */
    public function getDeduplicationKey(): string
    {
        return strtolower($this->email);
    }
}
