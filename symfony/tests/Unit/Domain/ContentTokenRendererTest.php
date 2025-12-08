<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Content\ContentTokenRenderer;
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

    public function testNormalizeLegacyContentRewritesDeprecatedTokens(): void
    {
        $content = 'Schedule: {{ timetable }}';

        self::assertSame(
            'Schedule: {{ dj_timetable }}',
            ContentTokenRenderer::normalizeLegacyContent($content)
        );
    }

    private function loadTemplate(string $source): TemplateWrapper
    {
        $this->twig->setLoader(new ArrayLoader(['test.html.twig' => $source]));

        return $this->twig->load('test.html.twig');
    }
}
