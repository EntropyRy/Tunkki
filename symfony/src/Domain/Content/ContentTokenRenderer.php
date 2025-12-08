<?php

declare(strict_types=1);

namespace App\Domain\Content;

use Twig\TemplateWrapper;

/**
 * Renders known content tokens into HTML using Twig blocks.
 */
final class ContentTokenRenderer
{
    /**
     * @var array<string, string|null>
     */
    private const array TOKEN_TO_BLOCK = [
        'dj_timetable' => 'dj_timetable',
        'timetable' => 'timetable',
        'timetable_to_page' => 'timetable_to_page',
        'timetable_to_page_with_genre' => 'timetable_to_page_with_genre',
        'timetable_with_genre' => 'timetable_with_genre',
        'vj_timetable' => 'vj_timetable',
        'vj_timetable_to_page' => 'vj_timetable_to_page',
        'dj_bio' => 'dj_bio',
        'bios' => 'bios',
        'vj_bio' => 'vj_bio',
        'vj_bios' => 'vj_bios',
        'streamplayer' => 'streamplayer',
        'links' => 'links',
        'rsvp' => 'RSVP',
        'stripe_ticket' => 'stripe_ticket',
        'ticket' => null,
        'art_artist_list' => 'art_artist_list',
        'happening_list' => 'happening_list',
        'menu' => null,
    ];

    /**
     * @var array<string, string>
     */
    public const array LEGACY_TAG_MAP = [
        '{{ timetable_to_page_with_genre }}' => '{{ dj_timetable }}',
        '{{ timetable_with_genre }}' => '{{ dj_timetable }}',
        '{{ timetable_to_page }}' => '{{ dj_timetable }}',
        '{{ timetable }}' => '{{ dj_timetable }}',
        '{{ bios }}' => '{{ dj_bio }}',
        '{{ vj_bios }}' => '{{ vj_bio }}',
        '{{ vj_timetable_to_page }}' => '{{ vj_timetable }}',
    ];

    /**
     * Render a token map (token → html) using template blocks.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, string>
     */
    public function renderMap(TemplateWrapper $template, array $context): array
    {
        $map = [];

        foreach (self::TOKEN_TO_BLOCK as $token => $block) {
            $search = \sprintf('{{ %s }}', $token);

            if (null === $block) {
                continue;
            }

            if ($template->hasBlock($block)) {
                $map[$search] = $template->renderBlock($block, $context);
            }
        }

        return $map;
    }

    /**
     * Replace known tokens in content using the provided template blocks.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $content, TemplateWrapper $template, array $context): string
    {
        $content = $this->stripNullTokens($content);
        $map = $this->renderMap($template, $context);

        return strtr($content, $map);
    }

    private function stripNullTokens(string $content): string
    {
        $remove = [];

        foreach (self::TOKEN_TO_BLOCK as $token => $block) {
            if (null === $block) {
                $remove[] = \sprintf('{{ %s }}', $token);
            }
        }

        if ([] === $remove) {
            return $content;
        }

        return str_replace($remove, '', $content);
    }

    /**
     * Normalize legacy tokens to canonical ones.
     */
    public static function normalizeLegacyContent(?string $content): ?string
    {
        if (null === $content) {
            return null;
        }

        return str_replace(
            array_keys(self::LEGACY_TAG_MAP),
            array_values(self::LEGACY_TAG_MAP),
            $content,
        );
    }

    /**
     * Strip all known token placeholders from content (after legacy normalization).
     */
    public static function stripTokens(?string $content): string
    {
        $normalized = self::normalizeLegacyContent((string) $content) ?? '';
        $placeholders = array_map(
            static fn (string $token): string => \sprintf('{{ %s }}', $token),
            array_keys(self::TOKEN_TO_BLOCK),
        );

        return str_replace($placeholders, '', $normalized);
    }
}
