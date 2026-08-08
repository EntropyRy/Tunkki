<?php

declare(strict_types=1);

namespace App\Domain\Content;

use League\CommonMark\GithubFlavoredMarkdownConverter;

use function Symfony\Component\String\u;

final class PlainTextContentFormatter
{
    private readonly GithubFlavoredMarkdownConverter $markdownConverter;

    public function __construct()
    {
        $this->markdownConverter = new GithubFlavoredMarkdownConverter([
            'renderer' => [
                'soft_break' => '<br>',
            ],
            'html_input' => 'allow',
        ]);
    }

    public function toPlainText(?string $content): string
    {
        $content = $this->stripPlaceholders(ContentTokenRenderer::stripTokens($content));

        if ('' === trim($content)) {
            return '';
        }

        $html = $this->markdownConverter->convert($content)->getContent();
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(?:blockquote|div|h[1-6]|li|p|tr)>/i', "\n", (string) $html);

        $plainText = html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $plainText = str_replace("\xc2\xa0", ' ', $plainText);
        $plainText = preg_replace('/[ \t]+/u', ' ', $plainText);
        $plainText = preg_replace('/[ \t]*\R[ \t]*/u', "\n", (string) $plainText);
        $plainText = preg_replace('/\R{3,}/u', "\n\n", (string) $plainText);

        return trim((string) $plainText);
    }

    public function excerpt(?string $content, int $length = 200, string $ellipsis = '..'): string
    {
        return (string) u($this->toPlainText($content))->truncate($length, $ellipsis);
    }

    private function stripPlaceholders(string $content): string
    {
        return (string) preg_replace('/\{\{\s*[^{}]*\s*\}\}/u', '', $content);
    }
}
