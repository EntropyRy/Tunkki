<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;

/**
 * Factory for creating a GitHub Flavored Markdown converter with soft breaks as <br>.
 *
 * This ensures frontend rendering matches Toast-UI admin preview behavior where
 * single newlines are displayed as line breaks rather than collapsed into spaces.
 */
final class GfmMarkdownConverterFactory
{
    public function __invoke(): GithubFlavoredMarkdownConverter
    {
        // GithubFlavoredMarkdownConverter includes: tables, strikethrough,
        // task lists, autolinks (matches Toast-UI features)
        return new GithubFlavoredMarkdownConverter([
            'renderer' => [
                'soft_break' => '<br>',
            ],
        ]);
    }
}
