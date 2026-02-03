<?php

declare(strict_types=1);

namespace App\Form;

use App\Domain\Content\ContentTokenRenderer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'format' => 'simple',  // default: simple editor (most common use)
            'heading_levels' => [2, 3, 4, 5, 6],
            'attr' => [],
        ]);
        $resolver->setAllowedValues('format', ['event', 'simple', 'telegram']);
        $resolver->setAllowedTypes('heading_levels', 'array');
        $resolver->setNormalizer('attr', function (
            Options $options,
            array $value,
        ): array {
            $base = [
                'data-controller' => 'markdown-editor',
                'data-markdown-editor-target' => 'textarea',
                'rows' => 20,
                'data-markdown-editor-heading-levels-value' => json_encode(
                    $options['heading_levels'],
                    \JSON_THROW_ON_ERROR,
                ),
                'data-markdown-editor-format-value' => $options['format'],
            ];

            if ('event' === $options['format']) {
                $base['data-markdown-editor-tokens-value'] = json_encode(array_keys(self::TOKEN_LABELS), \JSON_THROW_ON_ERROR);
                $base['data-markdown-editor-token-map-value'] = json_encode($this->buildTokenMap(), \JSON_THROW_ON_ERROR);
            } else {
                $base['data-markdown-editor-tokens-value'] = json_encode([], \JSON_THROW_ON_ERROR);
                $base['data-markdown-editor-token-map-value'] = json_encode([], \JSON_THROW_ON_ERROR);
            }

            return array_merge($base, $value);
        });
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
