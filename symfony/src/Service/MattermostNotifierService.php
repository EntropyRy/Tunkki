<?php

namespace App\Service;

use Symfony\Component\Notifier\Bridge\Mattermost\MattermostOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MattermostNotifierService
{
    public function __construct(
        private readonly ChatterInterface $chatter,
        private readonly ParameterBagInterface $params
    ) {
    }

    public function sendToMattermost(string $text, ?string $channel = null): void
    {
        $message = new ChatMessage($text);

        // Configure Mattermost-specific options
        $options = (new MattermostOptions())
            ->iconUrl($this->params->get('mm_tunkki_img'));

        // Override channel if specified (otherwise uses DSN default)
        if ($channel !== null && $this->params->get('kernel.environment') !== 'dev') {
            $options->channel($channel);
        }

        $message->options($options);

        // Send the message using the configured transport
        $this->chatter->send($message);
    }
}
