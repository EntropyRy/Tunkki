<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Content\ContentTokenRenderer;
use App\Domain\Content\PlainTextContentFormatter;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ContentTokenExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentTokenRenderer $renderer,
        private readonly PlainTextContentFormatter $plainTextContentFormatter,
    ) {
    }

    /**
     * @return TwigFilter[]
     */
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('content_plain_text', $this->contentPlainText(...)),
            new TwigFilter('content_excerpt', $this->contentExcerpt(...)),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'render_tokens',
                $this->renderTokens(...),
                ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderTokens(Environment $env, array $context, string $content): string
    {
        $template = $env->load('pieces/event.html.twig');

        return $this->renderer->render($content, $template, $context);
    }

    public function contentPlainText(?string $content): string
    {
        return $this->plainTextContentFormatter->toPlainText($content);
    }

    public function contentExcerpt(?string $content, int $length = 200, string $ellipsis = '..'): string
    {
        return $this->plainTextContentFormatter->excerpt($content, $length, $ellipsis);
    }
}
