<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\EmailPurpose;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Enum\EmailPurpose
 */
final class EmailPurposeTest extends TestCase
{
    public function testAllCasesHaveLabels(): void
    {
        foreach (EmailPurpose::cases() as $purpose) {
            $label = $purpose->label();

            $this->assertNotEmpty($label, \sprintf('Purpose "%s" must have a non-empty label', $purpose->value));
            $this->assertIsString($label);
        }
    }

    public function testRequiresEventReturnsCorrectValues(): void
    {
        // Event-related purposes require an event
        $eventPurposes = [
            EmailPurpose::RSVP,
            EmailPurpose::TICKET,
            EmailPurpose::NAKKIKONE,
            EmailPurpose::ARTIST,
            EmailPurpose::SELECTED_ARTIST,
            EmailPurpose::TICKET_QR,
        ];

        foreach ($eventPurposes as $purpose) {
            $this->assertTrue(
                $purpose->requiresEvent(),
                \sprintf('Purpose "%s" should require an event', $purpose->value)
            );
        }

        // Non-event purposes don't require an event
        $nonEventPurposes = [
            EmailPurpose::MEMBER_WELCOME,
            EmailPurpose::ACTIVE_MEMBER_THANK_YOU,
            EmailPurpose::AKTIIVIT,
            EmailPurpose::TIEDOTUS,
            EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE,
            EmailPurpose::VJ_ROSTER,
            EmailPurpose::DJ_ROSTER,
        ];

        foreach ($nonEventPurposes as $purpose) {
            $this->assertFalse(
                $purpose->requiresEvent(),
                \sprintf('Purpose "%s" should not require an event', $purpose->value)
            );
        }
    }

    public function testIsRecipientGroupIdentifiesOldEmailLists(): void
    {
        // Only aktiivit and tiedotus are old email lists
        $this->assertTrue(EmailPurpose::AKTIIVIT->isRecipientGroup());
        $this->assertTrue(EmailPurpose::TIEDOTUS->isRecipientGroup());

        // All other purposes are not recipient groups
        $otherPurposes = [
            EmailPurpose::MEMBER_WELCOME,
            EmailPurpose::ACTIVE_MEMBER_THANK_YOU,
            EmailPurpose::TICKET_QR,
            EmailPurpose::RSVP,
            EmailPurpose::TICKET,
            EmailPurpose::NAKKIKONE,
            EmailPurpose::ARTIST,
            EmailPurpose::SELECTED_ARTIST,
            EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE,
            EmailPurpose::VJ_ROSTER,
            EmailPurpose::DJ_ROSTER,
        ];

        foreach ($otherPurposes as $purpose) {
            $this->assertFalse(
                $purpose->isRecipientGroup(),
                \sprintf('Purpose "%s" should not be a recipient group', $purpose->value)
            );
        }
    }

    public function testEnumValuesMatchDatabaseStrings(): void
    {
        // Verify backing values match the old string-based DB data
        $expectedMappings = [
            'member' => EmailPurpose::MEMBER_WELCOME,
            'active_member' => EmailPurpose::ACTIVE_MEMBER_THANK_YOU,
            'ticket_qr' => EmailPurpose::TICKET_QR,
            'rsvp' => EmailPurpose::RSVP,
            'ticket' => EmailPurpose::TICKET,
            'nakkikone' => EmailPurpose::NAKKIKONE,
            'artist' => EmailPurpose::ARTIST,
            'selected_artist' => EmailPurpose::SELECTED_ARTIST,
            'aktiivit' => EmailPurpose::AKTIIVIT,
            'tiedotus' => EmailPurpose::TIEDOTUS,
            'active_member_info_package' => EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE,
            'vj_roster' => EmailPurpose::VJ_ROSTER,
            'dj_roster' => EmailPurpose::DJ_ROSTER,
        ];

        foreach ($expectedMappings as $dbValue => $enum) {
            $this->assertSame($dbValue, $enum->value, \sprintf('Enum %s should have DB value "%s"', $enum->name, $dbValue));
        }
    }

    public function testAllPurposesHaveUniqueValues(): void
    {
        $values = [];
        foreach (EmailPurpose::cases() as $purpose) {
            $this->assertNotContains(
                $purpose->value,
                $values,
                \sprintf('Purpose value "%s" is duplicated', $purpose->value)
            );
            $values[] = $purpose->value;
        }

        // Verify we have exactly 13 unique purposes
        $this->assertCount(13, $values, 'Should have exactly 13 unique email purposes');
    }
}
