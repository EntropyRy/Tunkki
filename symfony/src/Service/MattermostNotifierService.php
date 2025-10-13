<?php

declare(strict_types=1);

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
    ) {}

    public function sendToMattermost(
        string $text,
        ?string $channelKey = "yhdistys",
    ): void {
        $env = (string) $this->params->get("kernel.environment");
        $channels = $this->params->get("mattermost_channels");
        $channelId =
            $channelKey && isset($channels[$channelKey])
                ? $channels[$channelKey]
                : null;

        try {
            $message = new ChatMessage($text);
            $options = new MattermostOptions();

            if ($channelId && "dev" !== $env) {
                $options->recipient($channelId);
            }

            $message->options($options);
            $this->chatter->send($message);
        } catch (\Throwable) {
            // Silently ignore notification failures
        }
    }
}
