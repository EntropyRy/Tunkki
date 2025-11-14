<?php

declare(strict_types=1);

namespace App\Tests\Support\Csrf;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

/**
 * Test-only token storage that falls back to the shared session when no main request exists.
 */
final class RequestStackFallbackTokenStorage implements TokenStorageInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SessionInterface $fallbackSession,
        private readonly string $namespace = '_csrf',
    ) {
    }

    public function getToken(string $tokenId): string
    {
        $session = $this->getSession();
        $key = $this->namespacedKey($tokenId);

        if (!$session->has($key)) {
            throw new TokenNotFoundException(\sprintf('Unable to find a token for "%s".', $tokenId));
        }

        return (string) $session->get($key);
    }

    public function setToken(string $tokenId, #[\SensitiveParameter] string $token): void
    {
        $session = $this->getSession();
        $session->set($this->namespacedKey($tokenId), $token);
    }

    public function hasToken(string $tokenId): bool
    {
        return $this->getSession()->has($this->namespacedKey($tokenId));
    }

    public function removeToken(string $tokenId): ?string
    {
        return $this->getSession()->remove($this->namespacedKey($tokenId));
    }

    public function clear(): void
    {
        $session = $this->getSession();
        foreach (array_keys($session->all()) as $key) {
            if (str_starts_with($key, $this->namespace.'/')) {
                $session->remove($key);
            }
        }
    }

    private function namespacedKey(string $tokenId): string
    {
        return $this->namespace.'/'.$tokenId;
    }

    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && $request->hasSession()) {
            $session = $request->getSession();
        } else {
            $session = $this->fallbackSession;
        }

        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }
}
