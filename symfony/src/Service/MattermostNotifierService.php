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
        $message = new ChatMessage($text);

        // Configure Mattermost-specific options (iconUrl removed - not supported by current MattermostOptions)
        $options = new MattermostOptions();

        // Override channel if specified (otherwise uses DSN default)
        if (null !== $channel && 'dev' !== $this->params->get('kernel.environment')) {
            $options->channel($channel);
        }

        $message->options($options);

        // Send the message using the configured transport
        $this->chatter->send($message);
    }
}
