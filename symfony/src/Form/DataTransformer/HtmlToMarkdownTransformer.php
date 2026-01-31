<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Converts legacy HTML content to Markdown on form load.
 *
 * - transform(): Converts HTML (from DB) to Markdown (for editor) if HTML detected
 * - reverseTransform(): Passes through unchanged (stores as Markdown)
 *
 * Templates should use |markdown_to_html filter which accepts both formats.
 *
 * @implements DataTransformerInterface<string, string>
 */
final readonly class HtmlToMarkdownTransformer implements DataTransformerInterface
{
    private HtmlConverter $htmlToMd;

    public function __construct()
    {
        $this->htmlToMd = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => true,
        ]);
    }

    /**
     * Transform model data (from DB) to form view.
     * Converts HTML to Markdown if HTML is detected.
     */
    #[\Override]
    public function transform(mixed $value): string
    {
        if (!\is_string($value) || '' === $value) {
            return '';
        }

        // Detect if content is HTML (contains opening tags)
        if (preg_match('/<[a-z][\s\S]*>/i', $value)) {
            return $this->htmlToMd->convert($value);
        }

        return $value;
    }

    /**
     * Transform form view data back to model.
     * Passes through unchanged (stores as Markdown).
     */
    #[\Override]
    public function reverseTransform(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
