<?php

declare(strict_types=1);

namespace App\Form;

use App\Domain\Content\ContentTokenRenderer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * Wraps a textarea with the Toast UI markdown Stimulus controller.
 *
 * @extends AbstractType<array<mixed>>
 */
final class MarkdownEditorType extends AbstractType
{
    public function __construct(
        private readonly ContentTokenRenderer $tokenRenderer,
        private readonly Environment $twig,
    ) {
    }

    private const array TOKEN_LABELS = [
        'streamplayer' => 'Stream player',
        'links' => 'Links list',
        'dj_timetable' => 'DJ timetable',
        'vj_timetable' => 'VJ timetable',
        'dj_bio' => 'DJ bio',
        'vj_bio' => 'VJ bio',
        'rsvp' => 'RSVP',
        'stripe_ticket' => 'Stripe ticket',
        'art_artist_list' => 'Art artist list',
        'happening_list' => 'Happening list',
    ];

    #[\Override]
    public function getParent(): string
    {
        return TextareaType::class;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'data-controller' => 'markdown-editor',
                'data-markdown-editor-target' => 'textarea',
                'data-markdown-editor-tokens-value' => json_encode(array_keys(self::TOKEN_LABELS), \JSON_THROW_ON_ERROR),
                'data-markdown-editor-token-map-value' => json_encode($this->buildTokenMap(), \JSON_THROW_ON_ERROR),
                'rows' => 20,
            ],
        ]);
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['markdown_editor'] = true;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'markdown_editor';
    }

    /**
     * Provide simple HTML snippets for admin preview placeholders.
     *
     * @return array<string, string>
     */
    private function buildTokenMap(): array
    {
        $template = $this->twig->load('admin/markdown_token_preview.html.twig');

        // Convert search string "{{ token }}" to plain token key for JS
        $rendered = $this->tokenRenderer->renderMap($template, []);

        $map = [];
        foreach ($rendered as $search => $html) {
            $map[$this->stripBraces($search)] = $html;
        }

        return $map;
    }

    private function stripBraces(string $value): string
    {
        return trim(str_replace(['{', '}'], '', $value));
    }
}
