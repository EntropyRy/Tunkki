<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use App\Domain\EventTemporalStateService;
use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
        $this->primeRequestStackSession();
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

        self::assertMatchesRegularExpression('/Introduction text/', $rendered);
        self::assertMatchesRegularExpression('/Conclusion/', $rendered);
        self::assertDoesNotMatchRegularExpression('/\\{\\{\\s*dj_bio\\s*\\}\\}/', $rendered, 'Token should be replaced');
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
        $this->forceEventId($event, 123);
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

    private function forceEventId(Event $event, int $id): void
    {
        $ref = new \ReflectionProperty(Event::class, 'id');
        $ref->setValue($event, $id);
    }

    private function primeRequestStackSession(): void
    {
        $container = static::getContainer();
        if (!$container->has('request_stack')) {
            return;
        }

        $session = null;
        if ($container->has('session')) {
            $session = $container->get('session');
        } elseif ($container->has('session.factory')) {
            $session = $container->get('session.factory')->createSession();
        }

        if ($session instanceof SessionInterface && !$session->isStarted()) {
            $session->start();
        }

        $request = Request::create('/');
        $request->setLocale('fi');
        if ($request->hasSession()) {
            $request->getSession()->start();
        } elseif ($session instanceof SessionInterface) {
            $request->setSession($session);
        }

        $container->get('request_stack')->push($request);
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
