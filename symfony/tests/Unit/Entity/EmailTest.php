<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Email;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Email
 */
final class EmailTest extends TestCase
{
    public function testGetRecipientGroupsReturnsEmptyArrayWhenPropertyUninitialized(): void
    {
        $email = new Email();

        $unsetter = \Closure::bind(static function (Email $email): void {
            unset($email->recipientGroups);
        }, null, Email::class);

        $unsetter($email);

        $this->assertSame([], $email->getRecipientGroups());
    }
}
