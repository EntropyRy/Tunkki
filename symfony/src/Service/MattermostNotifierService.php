<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class MattermostNotifierService
{
    public function __construct(
        private readonly ChatterInterface $chatter,
        private readonly ParameterBagInterface $params,
    ) {
    }

    public function sendToMattermost(string $text, ?string $channel = null): void
    {
        $env = (string) $this->params->get('kernel.environment');

        try {
            $message = new ChatMessage($text);

            // Configure Mattermost-specific options
            $options = new MattermostOptions();

            // Override channel if specified (otherwise uses DSN default) and not in dev (original behavior)
            if (null !== $channel && 'dev' !== $env) {
                $options->recipient($channel);
            }

            $message->options($options);

            // Send the message using the configured transport (in test env this will hit in-memory transport)
            $this->chatter->send($message);
        } catch (\Throwable) {
            // Silently ignore notification failures; they must not break user flows.
        }
    }
}
