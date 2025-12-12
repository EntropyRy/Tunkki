<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use App\Domain\EventTemporalStateService;
use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Integration test for ContentTokenExtension Twig function.
 *
 * Tests the render_tokens() Twig function to ensure it properly loads
 * the template and renders content tokens.
 */
final class ContentTokenExtensionTest extends KernelTestCase
{
    private Environment $twig;
    private EventTemporalStateService $temporalState;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // Use the full Symfony Twig environment (includes all extensions)
        $this->twig = $container->get(Environment::class);
        $this->temporalState = $container->get(EventTemporalStateService::class);
    }

    public function testRenderTokensFunctionExists(): void
    {
        // Verify the function is registered
        $function = $this->twig->getFunction('render_tokens');

        self::assertNotNull($function, 'render_tokens function should be registered');
        self::assertSame('render_tokens', $function->getName());
    }

    public function testRenderTokensRendersContentWithTokens(): void
    {
        $event = $this->buildEvent();
        $content = "Introduction text\n\n{{ dj_bio }}\n\nConclusion";

        $template = $this->twig->createTemplate('{{ render_tokens(content) }}');
        $rendered = $template->render([
            'content' => $content,
            'event' => $event,
            'eventTemporalState' => $this->temporalState,
            'frontpage' => false,
            'app' => $this->createMockApp(),
        ]);

        self::assertStringContainsString('Introduction text', $rendered);
        self::assertStringContainsString('Conclusion', $rendered);
        self::assertStringNotContainsString('{{ dj_bio }}', $rendered, 'Token should be replaced');
    }

    public function testRenderTokensHandlesContentWithoutTokens(): void
    {
        $event = $this->buildEvent();
        $content = 'Just plain content without any tokens';

        $template = $this->twig->createTemplate('{{ render_tokens(content) }}');
        $rendered = $template->render([
            'content' => $content,
            'event' => $event,
            'eventTemporalState' => $this->temporalState,
            'frontpage' => false,
            'app' => $this->createMockApp(),
        ]);

        self::assertSame($content, $rendered);
    }

    public function testRenderTokensHandlesEmptyContent(): void
    {
        $event = $this->buildEvent();

        $template = $this->twig->createTemplate('{{ render_tokens(content) }}');
        $rendered = $template->render([
            'content' => '',
            'event' => $event,
            'eventTemporalState' => $this->temporalState,
            'frontpage' => false,
            'app' => $this->createMockApp(),
        ]);

        self::assertSame('', $rendered);
    }

    private function buildEvent(): Event
    {
        $now = new \DateTimeImmutable('2025-12-01T18:00:00+02:00');

        $event = new Event();
        $event
            ->setName('Test Event EN')
            ->setNimi('Testitapahtuma')
            ->setEventDate($now)
            ->setUntil($now->modify('+2 hours'))
            ->setUrl('test-event')
            ->setType('event')
            ->setPublishDate($now->modify('-1 day'))
            ->setPublished(true);

        $artist = (new Artist())
            ->setName('Test Artist')
            ->setBio('Bio FI')
            ->setBioEn('Bio EN')
            ->setGenre('Techno')
            ->setType('DJ');

        $info = (new EventArtistInfo())
            ->setArtistClone($artist)
            ->setStage('Main')
            ->setStartTime($now->modify('+30 minutes'));

        $event->addEventArtistInfo($info);

        return $event;
    }

    private function createMockApp(): object
    {
        return new class {
            public ?object $user = null;
            public object $request;

            public function __construct()
            {
                $this->request = new class {
                    public string $locale = 'fi';
                };
            }
        };
    }
}
