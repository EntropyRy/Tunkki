<?php

namespace App\Twig\Components;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use App\Helper\SSH;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class StreamControl
{
    private bool $streamStatus = false;

    public function __construct(
        private readonly SSH $ssh,
        private readonly TokenStorageInterface $tokenStorage
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
