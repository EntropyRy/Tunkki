<?php

declare(strict_types=1);

namespace App\Twig\Components\Stream;

use App\Helper\SSH;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Control
{
    private bool $streamStatus = false;

    public function __construct(
        private readonly SSH $ssh,
    ) {
    }

    public function mount(): void
    {
        // Check stream status when component is mounted
        $this->streamStatus = $this->ssh->checkStatus();
    }

    public function getStreamStatus(): bool
    {
        return $this->streamStatus;
    }
}
