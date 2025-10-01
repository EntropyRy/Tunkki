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
        // In dev environment, don't send to specific channels
        if ($this->params->get('kernel.environment') === 'dev') {
            $channel = null;
        }

        $message = new ChatMessage($text);

        // Configure Mattermost-specific options
        $options = (new MattermostOptions())
            ->username($this->params->get('mm_tunkki_botname'))
            ->iconUrl($this->params->get('mm_tunkki_img'));

        // Set channel if specified
        if ($channel !== null) {
            $options->channel($channel);
        }

        $message->options($options);

        // Send the message
        $this->chatter->send($message);
    }
}
