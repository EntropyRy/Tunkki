<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Content\ContentTokenRenderer;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TemplateWrapper;
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
    public function renderTokens(Environment $env, array $context, string $content, mixed $template = null): string
    {
        $template = $this->resolveTemplate($env, $template ?? ($context['_self'] ?? null));

        if (!$template instanceof TemplateWrapper || !$template->hasBlock('infos')) {
            $template = $this->fallbackTemplate($env);
        }

        if (!$template instanceof TemplateWrapper) {
            return $content;
        }

        return $this->renderer->render($content, $template, $context);
    }

    private function resolveTemplate(Environment $env, mixed $self): ?TemplateWrapper
    {
        if (null === $self) {
            return null;
        }

        try {
            if ($self instanceof TemplateWrapper) {
                return $self;
            }

            if (\is_object($self) && method_exists($self, 'getTemplateName')) {
                return $env->resolveTemplate($self->getTemplateName());
            }

            if (\is_string($self) && '' !== $self) {
                return $env->resolveTemplate($self);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function fallbackTemplate(Environment $env): ?TemplateWrapper
    {
        try {
            return $env->resolveTemplate('event.html.twig');
        } catch (\Throwable) {
            try {
                return $env->resolveTemplate('pieces/event.html.twig');
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
