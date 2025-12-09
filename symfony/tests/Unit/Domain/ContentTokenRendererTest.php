<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Content\ContentTokenRenderer;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TemplateWrapper;

final class ContentTokenRendererTest extends TestCase
{
    private ContentTokenRenderer $renderer;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->renderer = new ContentTokenRenderer();
        $this->twig = new Environment(new ArrayLoader());
    }

    public function testRenderReplacesTokensWithoutInjectingIndentation(): void
    {
        $template = $this->loadTemplate(<<<'TWIG'
{% block infos %}<div>Infos</div>{% endblock %}
{% block links -%}<p>Link A</p>
<p>Link B</p>{%- endblock %}
{% block stripe_ticket %}<div>Tickets</div>{% endblock %}
{% block dj_timetable %}<div>Timetable</div>{% endblock %}
TWIG);

        $content = "Intro\n{{ links }}\nBody {{ stripe_ticket }}\n{{ dj_timetable }}\nEnd";

        $rendered = $this->renderer->render($content, $template, []);

        $expected = "Intro\n<p>Link A</p>\n<p>Link B</p>\nBody <div>Tickets</div>\n<div>Timetable</div>\nEnd";
        self::assertSame($expected, $rendered);
    }

    public function testRenderStripsNullTokens(): void
    {
        $template = $this->loadTemplate('{% block infos %}info{% endblock %}');
        $content = 'Hello{{ menu }}World{{ ticket }}';

        $rendered = $this->renderer->render($content, $template, []);

        self::assertSame('HelloWorld', $rendered);
    }

    public function testRenderBiosBlockDoesNotIndent(): void
    {
        $template = $this->loadTemplate(<<<'TWIG'
{% block dj_bio -%}
<div class="bio">DJ Bio</div>
{%- endblock %}
TWIG);

        $rendered = $this->renderer->render("Hi\n{{ dj_bio }}\nBye", $template, []);

        self::assertStringNotContainsString("\n    <", $rendered);
        self::assertSame("Hi\n<div class=\"bio\">DJ Bio</div>\nBye", $rendered);
    }

    public function testMarkdownRenderingDoesNotProduceCodeBlock(): void
    {
        $template = $this->loadTemplate(<<<'TWIG'
{% block dj_bio -%}
<div class="bio">DJ Bio Content</div>
{%- endblock %}
TWIG);

        $content = "Intro\n\n{{ dj_bio }}\n\nOutro";
        $rendered = $this->renderer->render($content, $template, []);

        $converter = new GithubFlavoredMarkdownConverter();
        $html = (string) $converter->convert($rendered);

        self::assertStringNotContainsString('<pre><code>', $html, 'Bios should not be treated as code blocks by markdown');
        self::assertStringContainsString('<div class="bio">DJ Bio Content</div>', $html);
    }

    public function testNormalizeLegacyContentRewritesDeprecatedTokens(): void
    {
        $content = 'Schedule: {{ timetable }}';

        self::assertSame(
            'Schedule: {{ dj_timetable }}',
            ContentTokenRenderer::normalizeLegacyContent($content)
        );
    }

    public function testNormalizeLegacyContentReturnsNullForNull(): void
    {
        self::assertNull(ContentTokenRenderer::normalizeLegacyContent(null));
    }

    public function testStripTokensRemovesAllKnownTokens(): void
    {
        $content = 'Start {{ dj_timetable }} Middle {{ links }} {{ stripe_ticket }} End';

        $stripped = ContentTokenRenderer::stripTokens($content);

        self::assertSame('Start  Middle   End', $stripped);
    }

    public function testStripTokensHandlesNullInput(): void
    {
        self::assertSame('', ContentTokenRenderer::stripTokens(null));
    }

    public function testStripTokensNormalizesLegacyBeforeStripping(): void
    {
        $content = 'Content {{ timetable }} more {{ bios }}';

        $stripped = ContentTokenRenderer::stripTokens($content);

        // Legacy tokens {{ timetable }} and {{ bios }} should be normalized then stripped
        self::assertSame('Content  more ', $stripped);
    }

    public function testRenderMapReturnsEmptyForNullBlocks(): void
    {
        $template = $this->loadTemplate('{% block infos %}info{% endblock %}');

        $map = $this->renderer->renderMap($template, []);

        // Null blocks (ticket, menu) should not appear in map
        self::assertArrayNotHasKey('{{ ticket }}', $map);
        self::assertArrayNotHasKey('{{ menu }}', $map);
    }

    public function testRenderMapIncludesOnlyExistingBlocks(): void
    {
        $template = $this->loadTemplate(<<<'TWIG'
{% block links %}<a>Link</a>{% endblock %}
{% block dj_bio %}<div>Bio</div>{% endblock %}
TWIG);

        $map = $this->renderer->renderMap($template, []);

        self::assertArrayHasKey('{{ links }}', $map);
        self::assertArrayHasKey('{{ dj_bio }}', $map);
        self::assertSame('<a>Link</a>', $map['{{ links }}']);
        self::assertSame('<div>Bio</div>', $map['{{ dj_bio }}']);
    }

    public function testRenderWithContentHavingNoNullTokens(): void
    {
        $template = $this->loadTemplate('{% block links %}<a>Link</a>{% endblock %}');

        // Content with only non-null tokens (no {{ ticket }} or {{ menu }})
        $content = 'Start {{ links }} End';

        $rendered = $this->renderer->render($content, $template, []);

        // This hits line 102 in stripNullTokens() because $remove is empty
        self::assertSame('Start <a>Link</a> End', $rendered);
    }

    private function loadTemplate(string $source): TemplateWrapper
    {
        $this->twig->setLoader(new ArrayLoader(['test.html.twig' => $source]));

        return $this->twig->load('test.html.twig');
    }
}
