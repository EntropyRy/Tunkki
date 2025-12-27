<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\EPicsServiceInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component for displaying random photos from ePics gallery.
 *
 * Replaces the EPicsController API endpoint with a server-rendered component
 * that can be polled for updates.
 */
#[AsLiveComponent]
final class EPicsRandom
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?string $photoUrl = null;

    #[LiveProp]
    public ?string $takenAt = null;

    #[LiveProp]
    public string $defaultImage = '/images/header-logo.svg';

    #[LiveProp]
    public int $refreshInterval = 8000;

    #[LiveProp]
    public int $renderKey = 0;

    public function __construct(
        private readonly EPicsServiceInterface $epicsService,
    ) {
    }

    public function mount(
        string $defaultImage = '/images/header-logo.svg',
        int $refreshInterval = 8000,
    ): void {
        $this->defaultImage = $defaultImage;
        $this->refreshInterval = $refreshInterval;
        $this->loadRandomPhoto();
    }

    #[LiveAction]
    public function refresh(): void
    {
        $this->loadRandomPhoto();
    }

    public function getImageUrl(): string
    {
        return $this->photoUrl ?? $this->defaultImage;
    }

    public function hasPhoto(): bool
    {
        return null !== $this->photoUrl;
    }

    private function loadRandomPhoto(): void
    {
        $photo = $this->epicsService->getRandomPhoto();

        if (null !== $photo) {
            $this->photoUrl = $photo['url'] ?? null;
            $this->takenAt = $photo['taken'] ?? null;
        } else {
            $this->photoUrl = null;
            $this->takenAt = null;
        }

        // Increment to force animation restart on re-render
        ++$this->renderKey;
    }
}
