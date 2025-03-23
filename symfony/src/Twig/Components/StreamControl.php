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

    public function getCurrentDateTime(): string
    {
        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        return $dateTime->format('Y-m-d H:i:s');
    }
    public function getCurrentUser(): string
    {
        if (($token = $this->tokenStorage->getToken()) instanceof TokenInterface) {
            $user = $token->getUser();
            if (is_object($user)) {
                return $user->getUserIdentifier();
            }
        }

        return 'Guest';
    }

    public function isActiveMember(): bool
    {
        if (($token = $this->tokenStorage->getToken()) instanceof TokenInterface) {
            $user = $token->getUser();
            if (is_object($user) && method_exists($user, 'getMember')) {
                $member = $user->getMember();
                return $member && method_exists($member, 'getIsActiveMember') && $member->getIsActiveMember();
            }
        }

        return false;
    }
}
