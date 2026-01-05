<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

final class ThemeSwitcherTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClientHome('fi');
    }

    #[DataProvider('localeProvider')]
    public function testThemeSwitcherVisibleOnHomepage(string $locale): void
    {
        $this->seedClientHome($locale);
        $path = 'en' === $locale ? '/en' : '/';

        $this->client->request('GET', $path);

        $this->client->assertSelectorExists('.theme-switcher button');
    }

    #[DataProvider('localeProvider')]
    public function testThemeSwitcherVisibleOnDynamicThemeEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dynamic',
        ]);

        $this->seedClientHome($locale);
        $this->client->request('GET', $this->eventPath($event, $locale));

        $this->client->assertSelectorExists('.theme-switcher button');
    }

    #[DataProvider('localeProvider')]
    public function testThemeSwitcherHiddenOnFixedLightThemeEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'light',
        ]);

        $this->seedClientHome($locale);
        $this->client->request('GET', $this->eventPath($event, $locale));

        $this->client->assertSelectorNotExists('.theme-switcher');
    }

    #[DataProvider('localeProvider')]
    public function testThemeSwitcherHiddenOnFixedDarkThemeEvent(string $locale): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dark',
        ]);

        $this->seedClientHome($locale);
        $this->client->request('GET', $this->eventPath($event, $locale));

        $this->client->assertSelectorNotExists('.theme-switcher');
    }

    public function testThemeSwitcherVisibleForAuthenticatedUserOnHomepage(): void
    {
        $this->loginAsMember();
        $this->seedClientHome('fi');

        $this->client->request('GET', '/');

        $this->client->assertSelectorExists('.theme-switcher button');
    }

    public function testThemeSwitcherVisibleForAuthenticatedUserOnDynamicEvent(): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dynamic',
        ]);

        $this->loginAsMember();
        $this->seedClientHome('fi');

        $this->client->request('GET', $this->eventPath($event, 'fi'));

        $this->client->assertSelectorExists('.theme-switcher button');
    }

    public function testThemeSwitcherHiddenForAuthenticatedUserOnFixedThemeEvent(): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dark',
        ]);

        $this->loginAsMember();
        $this->seedClientHome('fi');

        $this->client->request('GET', $this->eventPath($event, 'fi'));

        $this->client->assertSelectorNotExists('.theme-switcher');
    }

    public function testFixedDarkThemeEventHasCorrectDataAttribute(): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dark',
        ]);

        $this->client->request('GET', $this->eventPath($event, 'fi'));

        $this->client->assertSelectorExists('html[data-bs-theme="dark"]');
    }

    public function testFixedLightThemeEventHasCorrectDataAttribute(): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'light',
        ]);

        $this->client->request('GET', $this->eventPath($event, 'fi'));

        $this->client->assertSelectorExists('html[data-bs-theme="light"]');
    }

    private function eventPath(Event $event, string $locale): string
    {
        $prefix = 'en' === $locale ? '/en' : '';

        return \sprintf(
            '%s/%s/%s',
            $prefix,
            $event->getEventDate()->format('Y'),
            $event->getUrl(),
        );
    }

    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }
}
