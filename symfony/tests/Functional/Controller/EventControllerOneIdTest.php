<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Zenstruck\Foundry\Test\Factories;

final class EventControllerOneIdTest extends FixturesWebTestCase
{
    use Factories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    #[DataProvider('preferredLanguageProvider')]
    public function testOneIdRedirectsToPreferredLanguageSlugUrl(
        string $idRouteLocale,
        string $preferredLanguage,
        string $expectedLocale,
    ): void {
        $event = EventFactory::new()
            ->published()
            ->create([
                'url' => 'id-redirect-'.uniqid('', true),
            ]);

        $this->seedClientHome($idRouteLocale);

        $id = $event->getId();
        $this->assertNotNull($id);

        $idPath = 'en' === $idRouteLocale
            ? \sprintf('/en/event/%d', $id)
            : \sprintf('/tapahtuma/%d', $id);

        $year = $event->getEventDate()->format('Y');
        $slug = $event->getUrl();
        $expected = 'en' === $expectedLocale
            ? \sprintf('/en/%s/%s', $year, $slug)
            : \sprintf('/%s/%s', $year, $slug);

        $this->client->request('GET', $idPath, server: [
            'HTTP_ACCEPT_LANGUAGE' => $preferredLanguage,
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame($expected, parse_url($location, \PHP_URL_PATH));
    }

    public static function preferredLanguageProvider(): array
    {
        return [
            // FI id route redirects to FI slug by default
            ['fi', 'fi', 'fi'],
            // FI id route redirects to EN slug when user prefers EN (telegram use-case)
            ['fi', 'en', 'en'],
            // EN id route redirects to EN slug by default
            ['en', 'en', 'en'],
            // EN id route redirects to FI slug when user prefers FI
            ['en', 'fi', 'fi'],
        ];
    }
}
