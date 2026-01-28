<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\AnnouncementsController;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AnnouncementsControllerTest extends FixturesWebTestCase
{
    #[DataProvider('localeProvider')]
    public function testAnnouncementsPageRenders(string $locale, string $path, string $expectedTitle): void
    {
        EventFactory::new()->published()->create([
            'type' => 'announcement',
            'name' => 'Announcement EN',
            'nimi' => 'Tiedote FI',
        ]);

        $response = $this->renderAnnouncements($locale, $path);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($expectedTitle, '/').'/',
            (string) $response->getContent()
        );
    }

    public static function localeProvider(): array
    {
        return [
            ['fi', '/tiedotukset', 'Tiedote FI'],
            ['en', '/en/announcements', 'Announcement EN'],
        ];
    }

    private function renderAnnouncements(string $locale, string $path): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create($path);
        $request->setLocale($locale);

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $session = $this->getSession();
        if ($session instanceof SessionInterface && !$session->isStarted()) {
            $session->start();
        }
        if ($session instanceof SessionInterface) {
            $request->setSession($session);
        }
        $requestStack->push($request);

        $controller = self::getContainer()->get(AnnouncementsController::class);
        $response = $controller->index(
            self::getContainer()->get(\App\Repository\EventRepository::class),
        );

        $requestStack->pop();

        return $response;
    }

    private function getSession(): ?SessionInterface
    {
        $container = self::getContainer();
        if ($container->has('session')) {
            $session = $container->get('session');

            return $session instanceof SessionInterface ? $session : null;
        }
        if ($container->has('session.factory')) {
            $session = $container->get('session.factory')->createSession();

            return $session instanceof SessionInterface ? $session : null;
        }

        return null;
    }
}
