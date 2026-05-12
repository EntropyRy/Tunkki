<?php

declare(strict_types=1);

namespace App\Webhook;

final class StripeEventNames
{
    public const PRICE_CREATED = 'stripe.price.created';
    public const PRICE_UPDATED = 'stripe.price.updated';
    public const PRICE_DELETED = 'stripe.price.deleted';
    public const PRODUCT_UPDATED = 'stripe.product.updated';
    public const CHECKOUT_SESSION_EXPIRED = 'stripe.checkout.session.expired';
    public const CHECKOUT_SESSION_COMPLETED = 'stripe.checkout.session.completed';
}
