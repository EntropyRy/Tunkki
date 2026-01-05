<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\Member;
use App\Factory\MemberFactory;
use App\Twig\Components\ThemeSwitcher;

final class ThemeSwitcherTest extends LiveComponentTestCase
{
    public function testAnonymousDefaultThemeIsLight(): void
    {
        $component = $this->mountComponent(ThemeSwitcher::class);

        /** @var ThemeSwitcher $state */
        $state = $component->component();
        self::assertSame('light', $state->theme);
    }

    public function testAnonymousToggleChangesTheme(): void
    {
        $component = $this->mountComponent(ThemeSwitcher::class);

        /** @var ThemeSwitcher $state */
        $state = $component->component();
        self::assertSame('light', $state->theme);

        $component->call('toggleTheme');

        $state = $component->component();
        self::assertSame('dark', $state->theme);
    }

    public function testAnonymousMultipleToggles(): void
    {
        $component = $this->mountComponent(ThemeSwitcher::class);

        /** @var ThemeSwitcher $state */
        $state = $component->component();
        self::assertSame('light', $state->theme);

        // Toggle to dark
        $component->call('toggleTheme');
        $state = $component->component();
        self::assertSame('dark', $state->theme);

        // Toggle back to light
        $component->call('toggleTheme');
        $state = $component->component();
        self::assertSame('light', $state->theme);

        // Toggle to dark again
        $component->call('toggleTheme');
        $state = $component->component();
        self::assertSame('dark', $state->theme);
    }

    public function testAuthenticatedUserTogglePersistsToMember(): void
    {
        $member = MemberFactory::new()->active()->create(['theme' => 'light']);
        $memberId = $member->getId();

        $component = $this->mountComponent(ThemeSwitcher::class, [
            'user' => $member->getUser(),
        ]);

        $component->call('toggleTheme');

        // Verify theme was toggled in component state
        /** @var ThemeSwitcher $state */
        $state = $component->component();
        self::assertSame('dark', $state->theme);

        // Verify member entity was updated in database
        $this->em()->clear();
        $reloadedMember = $this->em()->find(Member::class, $memberId);
        self::assertSame('dark', $reloadedMember->getTheme());
    }

    public function testAuthenticatedUserMultipleTogglesPersist(): void
    {
        $member = MemberFactory::new()->active()->create(['theme' => 'light']);
        $memberId = $member->getId();

        $component = $this->mountComponent(ThemeSwitcher::class, [
            'user' => $member->getUser(),
        ]);

        // Toggle to dark
        $component->call('toggleTheme');
        $this->em()->clear();
        $reloadedMember = $this->em()->find(Member::class, $memberId);
        self::assertSame('dark', $reloadedMember->getTheme());

        // Toggle to light
        $component->call('toggleTheme');
        $this->em()->clear();
        $reloadedMember = $this->em()->find(Member::class, $memberId);
        self::assertSame('light', $reloadedMember->getTheme());

        // Toggle to dark again
        $component->call('toggleTheme');
        $this->em()->clear();
        $reloadedMember = $this->em()->find(Member::class, $memberId);
        self::assertSame('dark', $reloadedMember->getTheme());
    }

    public function testComponentRendersButton(): void
    {
        $component = $this->mountComponent(ThemeSwitcher::class);

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.theme-switcher button')->count());
    }

    public function testComponentRendersIcon(): void
    {
        $component = $this->mountComponent(ThemeSwitcher::class);

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.theme-switcher button svg')->count());
    }
}
