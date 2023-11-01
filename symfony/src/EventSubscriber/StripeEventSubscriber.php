<?php

namespace App\EventSubscriber;

use App\Entity\Product;
use App\Helper\AppStripeClient;
use App\Repository\CheckoutRepository;
use App\Repository\ProductRepository;
use Fpt\StripeBundle\Event\StripeEvents;
use Fpt\StripeBundle\Event\StripeWebhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StripeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CheckoutRepository $cRepo,
        private readonly ProductRepository $pRepo,
        private readonly LoggerInterface $logger,
        private readonly AppStripeClient $stripe,
    ) {
    }
    public static function getSubscribedEvents(): array
    {
        return [
            StripeEvents::PRICE_CREATED => 'onPriceCreated',
            StripeEvents::PRICE_UPDATED => 'onPriceUpdated',
            StripeEvents::PRICE_DELETED => 'onPriceDeleted',
            StripeEvents::PRODUCT_UPDATED => 'onProductUpdated',
            StripeEvents::CHECKOUT_SESSION_EXPIRED => 'onCheckoutExpired',
        ];
    }

    public function onProductUpdated(StripeWebhook $webhook): void
    {
        /** @var \Stripe\Event $stripeEvent */
        $stripeEvent = $webhook->getStripeObject();
        /** @var \Stripe\Price $stripePrice */
        $stripeProduct = $stripeEvent->data->object;
        try {
            $products = $this->pRepo->findBy(['stripeId' => $stripeProduct['id']]);
            foreach ($products as $product) {
                $product = $this->stripe->updateOurProduct($product, null, $stripeProduct);
                $this->pRepo->save($product, true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceCreated(StripeWebhook $webhook): void
    {
        /** @var \Stripe\Event $stripeEvent */
        $stripeEvent = $webhook->getStripeObject();
        /** @var \Stripe\Price $stripePrice */
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = new Product();
            $product = $this->stripe->updateOurProduct($product, $stripePrice, null);
            $this->pRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceUpdated(StripeWebhook $webhook): void
    {
        /** @var \Stripe\Event $stripeEvent */
        $stripeEvent = $webhook->getStripeObject();
        /** @var \Stripe\Price $stripePrice */
        $stripePrice = $stripeEvent->data->object;
        try {
            $product = $this->pRepo->findOneBy(['stripePriceId' => $stripePrice->id]);
            $product = $this->stripe->updateOurProduct($product, $stripePrice, null);
            $this->pRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceDeleted(StripeWebhook $webhook): void
    {
        /** @var \Stripe\Event $stripeEvent */
        $stripeEvent = $webhook->getStripeObject();
        /** @var \Stripe\Price $stripePrice */
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = $this->pRepo->findOneBy(['stripePriceId' => $stripePrice->id]);
            $product->setActive(false);
            $this->pRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onCheckoutExpired(StripeWebhook $webhook): void
    {
        /** @var \Stripe\Event $stripeEvent */
        $stripeEvent = $webhook->getStripeObject();
        /** @var \Stripe\Subscription $subscriptionStripe */
        $session = $stripeEvent->data->object;
        $this->logger->notice('Session: ' . $session['id']);
        try {
            $checkout = $this->cRepo->findOneBy(['stripeSessionId' => $session['id']]);
            $this->logger->notice('Checkout: ' . $checkout->getStripeSessionId());
            $checkout->setStatus(-1);
            $this->cRepo->save($checkout, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
}
