<?php

declare(strict_types=1);

namespace App\Webhook;

use Stripe\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

final class StripeWebhookEvent extends SymfonyEvent
{
    private ?Response $response = null;

    public function __construct(
        private readonly Event $stripeObject,
    ) {
    }

    public function getStripeObject(): Event
    {
        return $this->stripeObject;
    }

    public function getType(): string
    {
        return $this->stripeObject->type;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }
}
