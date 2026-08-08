<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Content\PlainTextContentFormatter;
use PHPUnit\Framework\TestCase;

final class PlainTextContentFormatterTest extends TestCase
{
    private PlainTextContentFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new PlainTextContentFormatter();
    }

    public function testToPlainTextRemovesMarkdownAndTokenPlaceholders(): void
    {
        $content = "#### OPEN AIR | Helsinki\n\nKoko **kesän** {{ dj_timetable }} {{ unknown_token }} [Entropy](https://entropy.fi)";

        $plainText = $this->formatter->toPlainText($content);

        self::assertSame("OPEN AIR | Helsinki\n\nKoko kesän Entropy", $plainText);
    }

    public function testExcerptUsesCleanPlainTextBeforeTruncating(): void
    {
        $content = '#### Heading {{ links }} with **bold** content';

        self::assertSame('Heading with..', $this->formatter->excerpt($content, 14));
    }
}
