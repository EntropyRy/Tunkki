<?php

namespace App\Helper;

use App\Entity\Event;
use App\Entity\Product;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\Product as StripeProduct;
use Stripe\Price as StripePrice;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppStripeClient
{
    public function __construct(
        protected readonly ParameterBagInterface $bag,
        protected readonly UrlGeneratorInterface $urlG
    ) {
    }

    public function getClient(): StripeClient
    {
        return new StripeClient($this->bag->get('stripe_secret_key'));
    }

    public function getReturnUrl(Event $event): string
    {
        return $this->urlG->generate(
            'entropy_event_shop_complete',
            [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl()

            ],
            $this->urlG::ABSOLUTE_URL
        ) . '?session_id={CHECKOUT_SESSION_ID}';
    }

    public function updateOurProduct(
        Product $product,
        ?StripePrice $stripePrice,
        ?StripeProduct $stripeProduct,
    ): Product {
        if ($stripeProduct != null && $stripePrice == null) {
            $product->setActive($stripeProduct['active'] == 1 ? true : false);
            $product->setNameEn($stripeProduct['name']);
            $product->setStripeId($stripeProduct['id']);
            return $product;
        }
        if ($stripeProduct == null && $stripePrice != null) {
            $stripeProduct = $this->getClient()->products->retrieve($stripePrice['product']);
        }
        $active = true;
        if ($stripeProduct['active'] == 0 || $stripePrice['active'] == 0) {
            $active = false;
        }
        $product->setAmount($stripePrice['unit_amount']);
        $product->setActive($active);
        $product->setNameEn($stripeProduct['name']);
        $product->setStripeId($stripeProduct['id']);
        $product->setStripePriceId($stripePrice['id']);
        return $product;
    }

    public function getCheckoutSession($sessionId): Session
    {
        $stripe = $this->getClient();
        $session = $stripe->checkout->sessions->retrieve($sessionId);
        return $session;
    }
}
