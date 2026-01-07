<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class ThemeSwitcher
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public string $theme = 'light';

    #[LiveProp]
    public ?User $user = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function mount(): void
    {
        if ($this->user instanceof User) {
            $this->theme = $this->user->getMember()->getTheme() ?? 'light';

            return;
        }

        $this->theme = $this->getSessionTheme() ?? 'light';
    }

    #[LiveAction]
    public function toggleTheme(): void
    {
        // Re-read current theme from storage to ensure correct state
        $current = $this->user instanceof User
            ? ($this->user->getMember()->getTheme() ?? 'light')
            : ($this->getSessionTheme() ?? 'light');

        $next = 'dark' === $current ? 'light' : 'dark';
        $this->theme = $next;

        if ($this->user instanceof User) {
            $this->user->getMember()->setTheme($next);
            $this->entityManager->flush();
            $this->clearSessionTheme();
        } else {
            $this->setSessionTheme($next);
        }

        $this->dispatchBrowserEvent('theme:changed', ['theme' => $next]);
    }

    private function getSessionTheme(): ?string
    {
        $theme = $this->requestStack->getSession()->get('theme');

        return \is_string($theme) && '' !== $theme ? $theme : null;
    }

    private function setSessionTheme(string $theme): void
    {
        $this->requestStack->getSession()->set('theme', $theme);
    }

    private function clearSessionTheme(): void
    {
        $this->requestStack->getSession()->remove('theme');
    }
}
