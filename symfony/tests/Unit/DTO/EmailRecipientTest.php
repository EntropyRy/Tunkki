<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\EmailRecipient;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\DTO\EmailRecipient
 */
final class EmailRecipientTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $recipient = new EmailRecipient(
            email: 'test@example.com',
            locale: 'en',
            memberId: 42
        );

        $this->assertSame('test@example.com', $recipient->email);
        $this->assertSame('en', $recipient->locale);
        $this->assertSame(42, $recipient->memberId);
    }

    public function testGetDeduplicationKeyReturnsLowercaseEmail(): void
    {
        $recipient1 = new EmailRecipient('Test@Example.COM');
        $recipient2 = new EmailRecipient('test@example.com');
        $recipient3 = new EmailRecipient('OTHER@example.com');

        $this->assertSame('test@example.com', $recipient1->getDeduplicationKey());
        $this->assertSame('test@example.com', $recipient2->getDeduplicationKey());
        $this->assertSame('other@example.com', $recipient3->getDeduplicationKey());

        // Verify deduplication works (same key)
        $this->assertSame($recipient1->getDeduplicationKey(), $recipient2->getDeduplicationKey());
    }

    public function testDefaultLocaleIsFinnish(): void
    {
        $recipient = new EmailRecipient('test@example.com');

        $this->assertSame('fi', $recipient->locale);
    }

    public function testMemberIdIsNullableAndOptional(): void
    {
        $recipientWithoutMemberId = new EmailRecipient('test@example.com');
        $this->assertNull($recipientWithoutMemberId->memberId);

        $recipientWithMemberId = new EmailRecipient('test@example.com', 'fi', 123);
        $this->assertSame(123, $recipientWithMemberId->memberId);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        $recipient = new EmailRecipient('test@example.com');

        // Verify properties are readonly by checking reflection
        $reflection = new \ReflectionClass($recipient);

        $emailProperty = $reflection->getProperty('email');
        $this->assertTrue($emailProperty->isReadOnly());

        $localeProperty = $reflection->getProperty('locale');
        $this->assertTrue($localeProperty->isReadOnly());

        $memberIdProperty = $reflection->getProperty('memberId');
        $this->assertTrue($memberIdProperty->isReadOnly());
    }
}
