<?php

namespace App\Twig\Components\Stream;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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
