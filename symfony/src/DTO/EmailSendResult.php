<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\EmailPurpose;

/**
 * Result of an email sending operation.
 */
final readonly class EmailSendResult
{
    /**
     * @param array<EmailPurpose> $purposes
     * @param array<string>       $failedRecipients
     */
    public function __construct(
        public int $totalSent,
        public int $totalRecipients,
        public array $purposes,
        public array $failedRecipients = [],
        public ?\DateTimeImmutable $sentAt = null,
    ) {
    }

    public function getFailureCount(): int
    {
        return \count($this->failedRecipients);
    }
}
