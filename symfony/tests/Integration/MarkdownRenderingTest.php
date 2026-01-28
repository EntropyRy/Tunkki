<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;

final class MarkdownRenderingTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = static::getContainer()->get(Environment::class);
    }

    public function testSoftBreaksRenderAsBrTags(): void
    {
        $html = $this->render("Line one\nLine two");
        $crawler = new Crawler($html);

        self::assertCount(1, $crawler->filter('br'));
        self::assertStringContainsString('Line one', $html);
        self::assertStringContainsString('Line two', $html);
    }

    public function testDoubleNewlinesCreateParagraphs(): void
    {
        $html = $this->render("Paragraph one\n\nParagraph two");
        $crawler = new Crawler($html);

        self::assertCount(2, $crawler->filter('p'));
        self::assertSame('Paragraph one', $crawler->filter('p')->first()->text());
        self::assertSame('Paragraph two', $crawler->filter('p')->last()->text());
    }

    #[DataProvider('gfmFeatureProvider')]
    public function testGfmFeaturesAreSupported(string $markdown, string $expectedSelector): void
    {
        $html = $this->render($markdown);
        $crawler = new Crawler($html);

        self::assertCount(1, $crawler->filter($expectedSelector));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function gfmFeatureProvider(): array
    {
        return [
            'table' => ["| A | B |\n|---|---|\n| 1 | 2 |", 'table'],
            'strikethrough' => ['~~deleted~~', 'del'],
            'task list' => ['- [x] Done', 'input[type="checkbox"]'],
            'autolink' => ['https://example.com', 'a[href="https://example.com"]'],
        ];
    }

    private function render(string $markdown): string
    {
        return $this->twig->createTemplate('{{ content|markdown_to_html }}')
            ->render(['content' => $markdown]);
    }
}
