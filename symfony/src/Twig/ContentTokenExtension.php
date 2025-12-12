<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Content\ContentTokenRenderer;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ContentTokenExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentTokenRenderer $renderer,
    ) {
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
}
