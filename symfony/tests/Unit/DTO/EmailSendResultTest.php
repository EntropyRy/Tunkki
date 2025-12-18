<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\EmailSendResult;
use App\Enum\EmailPurpose;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\DTO\EmailSendResult
 */
final class EmailSendResultTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $purposes = [EmailPurpose::RSVP, EmailPurpose::TICKET];
        $failedRecipients = ['failed1@example.com', 'failed2@example.com'];
        $sentAt = new \DateTimeImmutable('2025-12-16 12:00:00');

        $result = new EmailSendResult(
            totalSent: 10,
            totalRecipients: 12,
            purposes: $purposes,
            failedRecipients: $failedRecipients,
            sentAt: $sentAt
        );

        $this->assertSame(10, $result->totalSent);
        $this->assertSame(12, $result->totalRecipients);
        $this->assertSame($purposes, $result->purposes);
        $this->assertSame($failedRecipients, $result->failedRecipients);
        $this->assertSame($sentAt, $result->sentAt);
    }

    public function testFailedRecipientsDefaultsToEmptyArray(): void
    {
        $result = new EmailSendResult(
            totalSent: 5,
            totalRecipients: 5,
            purposes: [EmailPurpose::AKTIIVIT]
        );

        $this->assertSame([], $result->failedRecipients);
        $this->assertIsArray($result->failedRecipients);
    }

    public function testSentAtDefaultsToNull(): void
    {
        $result = new EmailSendResult(
            totalSent: 5,
            totalRecipients: 5,
            purposes: [EmailPurpose::TIEDOTUS]
        );

        $this->assertNull($result->sentAt);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        $result = new EmailSendResult(
            totalSent: 1,
            totalRecipients: 1,
            purposes: [EmailPurpose::RSVP]
        );

        $reflection = new \ReflectionClass($result);

        $this->assertTrue($reflection->getProperty('totalSent')->isReadOnly());
        $this->assertTrue($reflection->getProperty('totalRecipients')->isReadOnly());
        $this->assertTrue($reflection->getProperty('purposes')->isReadOnly());
        $this->assertTrue($reflection->getProperty('failedRecipients')->isReadOnly());
        $this->assertTrue($reflection->getProperty('sentAt')->isReadOnly());
    }
}
