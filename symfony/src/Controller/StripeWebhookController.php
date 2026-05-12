<?php

declare(strict_types=1);

namespace App\Controller;

use App\Webhook\StripeWebhookEvent;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class StripeWebhookController
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
        #[Autowire('%stripe_check_signature%')] private bool $checkSignature,
        #[Autowire('%stripe_webhook_signature_key%')] private string $signatureKey,
    ) {
    }

    #[Route(['fi' => '/stripe/webhooks'], name: 'stripe_webhooks', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $content = (string) $request->getContent();

        if ($this->checkSignature) {
            $header = (string) $request->headers->get('STRIPE_SIGNATURE', '');
            try {
                WebhookSignature::verifyHeader($content, $header, $this->signatureKey);
            } catch (SignatureVerificationException $e) {
                $this->logger->error('Stripe signature verification failed', ['exception' => $e->getMessage()]);
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
        }

        try {
            $stripeEvent = Event::constructFrom((array) json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            $this->logger->error('Failed to decode Stripe webhook JSON', ['exception' => $e->getMessage()]);
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $this->logger->info('Stripe webhook received', [
            'event_id' => $stripeEvent->id,
            'event_type' => $stripeEvent->type,
        ]);

        $webhookEvent = new StripeWebhookEvent($stripeEvent);
        $this->dispatcher->dispatch($webhookEvent, \sprintf('stripe.%s', $stripeEvent->type));

        return $webhookEvent->getResponse() ?? new Response(status: Response::HTTP_NO_CONTENT);
    }
}
