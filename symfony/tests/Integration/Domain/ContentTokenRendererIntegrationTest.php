<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain;

use App\Domain\Content\ContentTokenRenderer;
use App\Domain\EventTemporalStateService;
use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use App\Entity\Happening;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class ContentTokenRendererIntegrationTest extends KernelTestCase
{
    private Environment $twig;
    private EventTemporalStateService $temporalState;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->twig = $container->get(Environment::class);
        $this->temporalState = $container->get(EventTemporalStateService::class);
    }

    public function testTokensRenderWithoutMarkdownCodeBlocks(): void
    {
        $event = $this->buildEvent();
        $template = $this->twig->load('pieces/event.html.twig');
        $renderer = new ContentTokenRenderer();

        $content = <<<MD
Intro

{{ timetable }}

{{ bios }}

{{ happening_list }}

{{ stripe_ticket }}

{{ rsvp }}
MD;

        $context = $this->buildContext($event);

        $rendered = $renderer->render($content, $template, $context);

        $html = (string) (new GithubFlavoredMarkdownConverter())->convert($rendered);

        self::assertStringNotContainsString('<pre><code>', $html, 'Rendered tokens must not be treated as code blocks');
        self::assertStringContainsString('id="timetable"', $html);
        self::assertStringContainsString('Test Artist', $html);
        self::assertStringContainsString('Bio FI', $html);
        self::assertStringContainsString('id="happening_list"', $html);
        self::assertStringContainsString('id="ticket"', $html);
        self::assertStringContainsString('id="RSVP"', $html);
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
            ->setPublished(true)
            ->setTicketsEnabled(true)
            ->setTicketPresaleStart($now->modify('-1 day'))
            ->setTicketPresaleEnd($now->modify('+1 day'))
            ->setRsvpSystemEnabled(true)
            ->setAllowMembersToCreateHappenings(true)
            ->setIncludeSaferSpaceGuidelines(false);

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

        $happening = (new Happening())
            ->setNameFi('Sauna')
            ->setNameEn('Sauna')
            ->setSlugFi('sauna')
            ->setSlugEn('sauna')
            ->setEvent($event)
            ->setTime($now->modify('+1 hour'))
            ->setReleaseThisHappeningInEvent(true)
            ->setNeedsPreliminarySignUp(false);

        $event->addHappening($happening);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Event $event): array
    {
        $request = new Request();
        $request->setLocale('fi');

        $app = new class($request) {
            public ?object $user = null;
            public Request $request;

            public function __construct(Request $request)
            {
                $this->request = $request;
            }
        };

        return [
            'event' => $event,
            'eventTemporalState' => $this->temporalState,
            'tickets' => [],
            'frontpage' => true,
            'app' => $app,
        ];
    }
}
