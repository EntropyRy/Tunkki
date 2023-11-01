<?php

namespace App\Helper;

use App\Entity\Product;
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

    public function getReturnUrl(): string
    {
        return $this->urlG->generate('stripe_complete', [], $this->urlG::ABSOLUTE_URL);
    }

    public function updateOurProduct(
        Product $product,
        ?StripePrice $stripePrice,
        ?StripeProduct $stripeProduct,
    ): Product {
        if ($stripeProduct != null && $stripePrice == null) {
            $product->setActive($stripeProduct['active'] == 1 ? true : false);
            $product->setName($stripeProduct['name']);
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
        if (($stripePrice['custom_unit_amount'] != null)) {
            $product->setCustomAmount($stripePrice['custom_unit_amount']->toArray());
        } else {
            $product->setAmount($stripePrice['unit_amount']);
        }
        $product->setActive($active);
        $product->setName($stripeProduct['name']);
        $product->setStripeId($stripeProduct['id']);
        $product->setStripePriceId($stripePrice['id']);
        $product->setStripeData([
            'product' => $stripeProduct->toArray(),
            'price' => $stripePrice->toArray()
        ]);
        return $product;
    }
}
