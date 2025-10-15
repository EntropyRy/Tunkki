<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Product;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StripeService
{
    public function __construct(
        private ParameterBagInterface $bag,
        private UrlGeneratorInterface $urlG,
    ) {
    }

    public function getClient(): StripeClient
    {
        return new StripeClient($this->bag->get('stripe_secret_key'));
    }

    public function getReturnUrl(?Event $event): string
    {
        if (null == $event) {
            return $this->urlG->generate(
                'entropy_shop_complete',
                [],
                $this->urlG::ABSOLUTE_URL
            ).'?session_id={CHECKOUT_SESSION_ID}';
        }

        return $this->urlG->generate(
            'entropy_event_shop_complete',
            [
                'year' => $event->getEventDate()->format('Y'),
                'slug' => $event->getUrl(),
            ],
            $this->urlG::ABSOLUTE_URL
        ).'?session_id={CHECKOUT_SESSION_ID}';
    }

    public function updateOurProduct(
        Product $product,
        ?StripeObject $stripePrice,
        ?StripeObject $stripeProduct,
    ): Product {
        if (null != $stripeProduct && null == $stripePrice) {
            $product->setActive(1 == $stripeProduct['active']);
            $product->setNameEn($stripeProduct['name']);
            $product->setStripeId($stripeProduct['id']);

            return $product;
        }
        if (null == $stripeProduct && null != $stripePrice) {
            $stripeProduct = $this->getClient()->products->retrieve($stripePrice['product']);
        }
        $active = true;
        if (0 == $stripeProduct['active'] || 0 == $stripePrice['active']) {
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

        return $stripe->checkout->sessions->retrieve($sessionId);
    }
}
