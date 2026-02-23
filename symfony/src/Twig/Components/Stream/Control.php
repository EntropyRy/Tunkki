<?php

declare(strict_types=1);

namespace App\Twig\Components\Stream;

use App\Service\SSHServiceInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Control
{
    private bool $streamStatus = false;

    public function __construct(
        private readonly SSHServiceInterface $ssh,
    ) {
    }

    public function mount(): void
    {
        // Never block page rendering on SSH connectivity problems.
        try {
            $this->streamStatus = $this->ssh->checkStatus();
        } catch (\Throwable) {
            $this->streamStatus = false;
        }
    }

    public function getStreamStatus(): bool
    {
        return $this->streamStatus;
    }
}
