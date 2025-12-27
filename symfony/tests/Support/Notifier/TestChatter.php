<?php

declare(strict_types=1);

namespace App\Tests\Support\Notifier;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * Test chatter that captures sent messages and can be configured to throw.
 */
final class TestChatter implements ChatterInterface
{
    /** @var MessageInterface[] */
    public array $messages = [];

    private bool $shouldThrow = false;

    public function reset(): void
    {
        $this->messages = [];
        $this->shouldThrow = false;
    }

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function send(
        MessageInterface $message,
        ?string $transportName = null,
    ): ?SentMessage {
        if ($this->shouldThrow) {
            throw new \RuntimeException('Chatter send failed (test)');
        }

        $this->messages[] = $message;

        return null;
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }

    public function __toString(): string
    {
        return 'test://chatter';
    }
}
