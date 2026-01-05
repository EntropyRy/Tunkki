<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Symfony\Component\Routing\RouterInterface;

final class ThemeSwitcherTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    private RouterInterface $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClientHome('fi');
        $this->router = static::getContainer()->get(RouterInterface::class);
    }

    public function testThemeSwitcherToggleOnHomeForAnonymous(): void
    {
        $this->client->request('GET', '/');

        $this->client->assertSelectorExists(
            'button[data-controller~="theme-switcher"]',
        );
        $this->client->assertSelectorNotExists(
            \sprintf('a[href="%s"]', $this->profileEditHref('fi')),
        );
    }

    public function testThemeSwitcherToggleOnDynamicEventForAnonymous(): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dynamic',
        ]);

        $this->client->request('GET', $this->eventPath($event));

        $this->client->assertSelectorExists(
            'button[data-controller~="theme-switcher"]',
        );
        $this->client->assertSelectorNotExists(
            \sprintf('a[href="%s"]', $this->profileEditHref('fi')),
        );
    }

    public function testThemeSwitcherLinkOnDynamicEventForAuthenticatedUser(): void
    {
        $event = EventFactory::new()->published()->create([
            'theme' => 'dynamic',
        ]);

        $this->loginAsMember();
        $this->seedClientHome('fi');

        $this->client->request('GET', $this->eventPath($event));

        $this->client->assertSelectorExists(
            \sprintf('a[href="%s"]', $this->profileEditHref('fi')),
        );
        $this->client->assertSelectorNotExists(
            'button[data-controller~="theme-switcher"]',
        );
    }

    private function profileEditHref(string $locale): string
    {
        return $this->router->generate('profile_edit', [
            '_locale' => $locale,
        ]).'#theme';
    }

    private function eventPath(Event $event): string
    {
        return \sprintf(
            '/%s/%s',
            $event->getEventDate()->format('Y'),
            $event->getUrl(),
        );
    }
}
